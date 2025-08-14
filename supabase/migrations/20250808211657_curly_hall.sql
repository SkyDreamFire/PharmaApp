/*
  # Système de gestion des lots pour FIFO par date d'expiration

  1. Nouvelle Table
    - `lots_medicaments` - Gestion des lots avec dates d'expiration
    
  2. Modifications
    - Ajout de fonctions pour gérer les lots automatiquement
    - Triggers pour maintenir la cohérence des stocks
    
  3. Fonctionnalités
    - Gestion automatique des lots lors des entrées
    - Sortie FIFO basée sur les dates d'expiration
    - Calcul automatique de la prochaine date d'expiration
*/

-- Table des lots de médicaments
CREATE TABLE IF NOT EXISTS lots_medicaments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  medicament_id uuid REFERENCES medicaments(id) ON DELETE CASCADE NOT NULL,
  numero_lot text,
  quantite_initiale integer NOT NULL DEFAULT 0,
  quantite_restante integer NOT NULL DEFAULT 0,
  date_expiration date NOT NULL,
  date_entree timestamptz DEFAULT now(),
  prix_achat decimal(10,2),
  fournisseur_id uuid REFERENCES fournisseurs(id),
  user_id uuid REFERENCES users(id) NOT NULL,
  actif boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Index pour optimiser les requêtes FIFO
CREATE INDEX IF NOT EXISTS idx_lots_medicament_expiration ON lots_medicaments(medicament_id, date_expiration, quantite_restante);
CREATE INDEX IF NOT EXISTS idx_lots_expiration_active ON lots_medicaments(date_expiration, actif) WHERE quantite_restante > 0;

-- Activation RLS
ALTER TABLE lots_medicaments ENABLE ROW LEVEL SECURITY;

-- Politique RLS pour lots_medicaments
CREATE POLICY "All authenticated users can read lots_medicaments" ON lots_medicaments
  FOR SELECT TO authenticated
  USING (true);

CREATE POLICY "Admin and vendeur can manage lots_medicaments" ON lots_medicaments
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

-- Fonction pour obtenir la prochaine date d'expiration d'un médicament
CREATE OR REPLACE FUNCTION get_next_expiration_date(medicament_uuid uuid)
RETURNS date AS $$
DECLARE
  next_expiration date;
BEGIN
  SELECT date_expiration INTO next_expiration
  FROM lots_medicaments
  WHERE medicament_id = medicament_uuid 
    AND quantite_restante > 0 
    AND actif = true
  ORDER BY date_expiration ASC
  LIMIT 1;
  
  RETURN next_expiration;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour obtenir les lots disponibles d'un médicament (triés par expiration)
CREATE OR REPLACE FUNCTION get_available_lots(medicament_uuid uuid)
RETURNS TABLE(
  lot_id uuid,
  numero_lot text,
  quantite_restante integer,
  date_expiration date,
  prix_achat decimal(10,2)
) AS $$
BEGIN
  RETURN QUERY
  SELECT 
    l.id,
    l.numero_lot,
    l.quantite_restante,
    l.date_expiration,
    l.prix_achat
  FROM lots_medicaments l
  WHERE l.medicament_id = medicament_uuid 
    AND l.quantite_restante > 0 
    AND l.actif = true
  ORDER BY l.date_expiration ASC, l.date_entree ASC;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour créer un nouveau lot
CREATE OR REPLACE FUNCTION create_lot(
  p_medicament_id uuid,
  p_quantite integer,
  p_date_expiration date,
  p_numero_lot text DEFAULT NULL,
  p_prix_achat decimal(10,2) DEFAULT NULL,
  p_fournisseur_id uuid DEFAULT NULL,
  p_user_id uuid DEFAULT NULL
)
RETURNS uuid AS $$
DECLARE
  lot_id uuid;
BEGIN
  INSERT INTO lots_medicaments (
    medicament_id,
    numero_lot,
    quantite_initiale,
    quantite_restante,
    date_expiration,
    prix_achat,
    fournisseur_id,
    user_id
  ) VALUES (
    p_medicament_id,
    p_numero_lot,
    p_quantite,
    p_quantite,
    p_date_expiration,
    p_prix_achat,
    p_fournisseur_id,
    COALESCE(p_user_id, auth.uid())
  )
  RETURNING id INTO lot_id;
  
  RETURN lot_id;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour effectuer une sortie FIFO
CREATE OR REPLACE FUNCTION sortie_fifo(
  p_medicament_id uuid,
  p_quantite_demandee integer,
  p_user_id uuid,
  p_motif text DEFAULT 'Vente'
)
RETURNS TABLE(
  lot_id uuid,
  quantite_sortie integer,
  date_expiration date,
  prix_achat decimal(10,2)
) AS $$
DECLARE
  lot_record RECORD;
  quantite_restante_demandee integer := p_quantite_demandee;
  quantite_a_sortir integer;
BEGIN
  -- Parcourir les lots par ordre d'expiration (FIFO)
  FOR lot_record IN 
    SELECT l.id, l.quantite_restante, l.date_expiration, l.prix_achat
    FROM lots_medicaments l
    WHERE l.medicament_id = p_medicament_id 
      AND l.quantite_restante > 0 
      AND l.actif = true
    ORDER BY l.date_expiration ASC, l.date_entree ASC
  LOOP
    -- Calculer la quantité à sortir de ce lot
    quantite_a_sortir := LEAST(lot_record.quantite_restante, quantite_restante_demandee);
    
    -- Mettre à jour la quantité restante du lot
    UPDATE lots_medicaments 
    SET quantite_restante = quantite_restante - quantite_a_sortir,
        updated_at = now()
    WHERE id = lot_record.id;
    
    -- Enregistrer le mouvement de stock pour ce lot
    INSERT INTO mouvements_stock (
      medicament_id,
      type,
      quantite,
      quantite_avant,
      quantite_apres,
      motif,
      reference_type,
      user_id
    ) VALUES (
      p_medicament_id,
      'sortie',
      quantite_a_sortir,
      lot_record.quantite_restante,
      lot_record.quantite_restante - quantite_a_sortir,
      p_motif || ' - Lot exp: ' || lot_record.date_expiration,
      'lot_fifo',
      p_user_id
    );
    
    -- Retourner les informations du lot utilisé
    RETURN QUERY SELECT 
      lot_record.id,
      quantite_a_sortir,
      lot_record.date_expiration,
      lot_record.prix_achat;
    
    -- Réduire la quantité restante demandée
    quantite_restante_demandee := quantite_restante_demandee - quantite_a_sortir;
    
    -- Si on a satisfait la demande, sortir de la boucle
    EXIT WHEN quantite_restante_demandee <= 0;
  END LOOP;
  
  -- Vérifier si on a pu satisfaire toute la demande
  IF quantite_restante_demandee > 0 THEN
    RAISE EXCEPTION 'Stock insuffisant. Manque % unités', quantite_restante_demandee;
  END IF;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour calculer le stock total d'un médicament
CREATE OR REPLACE FUNCTION calculate_total_stock(medicament_uuid uuid)
RETURNS integer AS $$
DECLARE
  total_stock integer;
BEGIN
  SELECT COALESCE(SUM(quantite_restante), 0) INTO total_stock
  FROM lots_medicaments
  WHERE medicament_id = medicament_uuid 
    AND quantite_restante > 0 
    AND actif = true;
  
  RETURN total_stock;
END;
$$ LANGUAGE plpgsql;

-- Trigger pour maintenir la cohérence du stock dans la table medicaments
CREATE OR REPLACE FUNCTION sync_medicament_stock()
RETURNS TRIGGER AS $$
BEGIN
  -- Mettre à jour le stock total et la prochaine date d'expiration
  UPDATE medicaments 
  SET 
    quantite_stock = calculate_total_stock(COALESCE(NEW.medicament_id, OLD.medicament_id)),
    date_expiration = get_next_expiration_date(COALESCE(NEW.medicament_id, OLD.medicament_id)),
    updated_at = now()
  WHERE id = COALESCE(NEW.medicament_id, OLD.medicament_id);
  
  RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

-- Triggers pour synchroniser automatiquement les stocks
CREATE TRIGGER trigger_sync_stock_after_lot_insert
  AFTER INSERT ON lots_medicaments
  FOR EACH ROW EXECUTE FUNCTION sync_medicament_stock();

CREATE TRIGGER trigger_sync_stock_after_lot_update
  AFTER UPDATE ON lots_medicaments
  FOR EACH ROW EXECUTE FUNCTION sync_medicament_stock();

CREATE TRIGGER trigger_sync_stock_after_lot_delete
  AFTER DELETE ON lots_medicaments
  FOR EACH ROW EXECUTE FUNCTION sync_medicament_stock();

-- Vue pour afficher les informations complètes des médicaments avec leurs lots
CREATE OR REPLACE VIEW medicaments_with_lots AS
SELECT 
  m.*,
  l.lots_info,
  l.total_lots,
  l.lots_expires_soon
FROM medicaments m
LEFT JOIN (
  SELECT 
    medicament_id,
    json_agg(
      json_build_object(
        'id', id,
        'numero_lot', numero_lot,
        'quantite_restante', quantite_restante,
        'date_expiration', date_expiration,
        'jours_avant_expiration', date_expiration - CURRENT_DATE,
        'prix_achat', prix_achat
      ) ORDER BY date_expiration ASC
    ) FILTER (WHERE quantite_restante > 0) as lots_info,
    COUNT(*) FILTER (WHERE quantite_restante > 0) as total_lots,
    COUNT(*) FILTER (WHERE quantite_restante > 0 AND date_expiration <= CURRENT_DATE + INTERVAL '30 days') as lots_expires_soon
  FROM lots_medicaments
  WHERE actif = true
  GROUP BY medicament_id
) l ON m.id = l.medicament_id;

-- Trigger pour mettre à jour updated_at sur lots_medicaments
CREATE TRIGGER update_lots_medicaments_updated_at 
  BEFORE UPDATE ON lots_medicaments
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();