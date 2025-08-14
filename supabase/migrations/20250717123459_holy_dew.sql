/*
  # Schéma complet pour le système de gestion de pharmacie

  1. Nouvelles Tables
    - `users` - Utilisateurs du système avec rôles
    - `clients` - Clients de la pharmacie
    - `fournisseurs` - Fournisseurs de médicaments
    - `medicaments` - Catalogue des médicaments
    - `ventes` - Transactions de vente
    - `details_vente` - Détails des ventes (ligne par médicament)
    - `commandes_fournisseur` - Commandes aux fournisseurs
    - `details_commande_fournisseur` - Détails des commandes
    - `notifications` - Système de notifications
    - `mouvements_stock` - Historique des mouvements de stock

  2. Sécurité
    - RLS activé sur toutes les tables
    - Politiques basées sur les rôles utilisateur
    - Authentification Supabase intégrée

  3. Fonctionnalités
    - Gestion complète du stock
    - Système d'alertes automatiques
    - Traçabilité des mouvements
    - Facturation et historique des ventes
*/

-- Extension pour UUID
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Table des utilisateurs (profils étendus)
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email text UNIQUE NOT NULL,
  role text NOT NULL CHECK (role IN ('admin', 'vendeur', 'comptable')) DEFAULT 'vendeur',
  nom text NOT NULL,
  prenom text NOT NULL,
  telephone text,
  actif boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Table des clients
CREATE TABLE IF NOT EXISTS clients (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  nom text NOT NULL,
  prenom text NOT NULL,
  telephone text NOT NULL,
  email text,
  adresse text,
  date_naissance date,
  points_fidelite integer DEFAULT 0,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Table des fournisseurs
CREATE TABLE IF NOT EXISTS fournisseurs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  nom text NOT NULL,
  contact text NOT NULL,
  telephone text NOT NULL,
  email text,
  adresse text,
  actif boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Table des médicaments
CREATE TABLE IF NOT EXISTS medicaments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  nom text NOT NULL,
  code_barre text UNIQUE,
  categorie text NOT NULL,
  forme text NOT NULL,
  dosage text,
  prix_achat decimal(10,2) NOT NULL DEFAULT 0,
  prix_vente decimal(10,2) NOT NULL DEFAULT 0,
  quantite_stock integer NOT NULL DEFAULT 0,
  seuil_alerte integer NOT NULL DEFAULT 10,
  date_expiration date,
  fournisseur_id uuid REFERENCES fournisseurs(id),
  prescription_requise boolean DEFAULT false,
  actif boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Table des ventes
CREATE TABLE IF NOT EXISTS ventes (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  facture_numero text UNIQUE NOT NULL,
  client_id uuid REFERENCES clients(id),
  vendeur_id uuid REFERENCES users(id) NOT NULL,
  sous_total decimal(10,2) NOT NULL DEFAULT 0,
  tva decimal(10,2) NOT NULL DEFAULT 0,
  total decimal(10,2) NOT NULL DEFAULT 0,
  mode_paiement text NOT NULL CHECK (mode_paiement IN ('especes', 'carte', 'cheque', 'virement')) DEFAULT 'especes',
  statut text NOT NULL CHECK (statut IN ('en_cours', 'validee', 'annulee')) DEFAULT 'validee',
  date_vente timestamptz DEFAULT now(),
  created_at timestamptz DEFAULT now()
);

-- Table des détails de vente
CREATE TABLE IF NOT EXISTS details_vente (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  vente_id uuid REFERENCES ventes(id) ON DELETE CASCADE NOT NULL,
  medicament_id uuid REFERENCES medicaments(id) NOT NULL,
  quantite integer NOT NULL DEFAULT 1,
  prix_unitaire decimal(10,2) NOT NULL,
  sous_total decimal(10,2) NOT NULL,
  created_at timestamptz DEFAULT now()
);

-- Table des commandes fournisseur
CREATE TABLE IF NOT EXISTS commandes_fournisseur (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  numero_commande text UNIQUE NOT NULL,
  fournisseur_id uuid REFERENCES fournisseurs(id) NOT NULL,
  user_id uuid REFERENCES users(id) NOT NULL,
  date_commande timestamptz DEFAULT now(),
  date_livraison_prevue date,
  date_livraison_reelle date,
  statut text NOT NULL CHECK (statut IN ('en_attente', 'confirmee', 'livree', 'annulee')) DEFAULT 'en_attente',
  sous_total decimal(10,2) NOT NULL DEFAULT 0,
  tva decimal(10,2) NOT NULL DEFAULT 0,
  total decimal(10,2) NOT NULL DEFAULT 0,
  notes text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Table des détails de commande fournisseur
CREATE TABLE IF NOT EXISTS details_commande_fournisseur (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  commande_id uuid REFERENCES commandes_fournisseur(id) ON DELETE CASCADE NOT NULL,
  medicament_id uuid REFERENCES medicaments(id) NOT NULL,
  quantite integer NOT NULL DEFAULT 1,
  prix_unitaire decimal(10,2) NOT NULL,
  sous_total decimal(10,2) NOT NULL,
  quantite_recue integer DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  type text NOT NULL CHECK (type IN ('stock_faible', 'expiration', 'commande', 'vente', 'system')),
  titre text NOT NULL,
  message text NOT NULL,
  lu boolean DEFAULT false,
  user_id uuid REFERENCES users(id),
  medicament_id uuid REFERENCES medicaments(id),
  commande_id uuid REFERENCES commandes_fournisseur(id),
  vente_id uuid REFERENCES ventes(id),
  created_at timestamptz DEFAULT now()
);

-- Table des mouvements de stock
CREATE TABLE IF NOT EXISTS mouvements_stock (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  medicament_id uuid REFERENCES medicaments(id) NOT NULL,
  type text NOT NULL CHECK (type IN ('entree', 'sortie', 'ajustement', 'expiration')),
  quantite integer NOT NULL,
  quantite_avant integer NOT NULL,
  quantite_apres integer NOT NULL,
  motif text NOT NULL,
  reference_id uuid, -- ID de la vente, commande, etc.
  reference_type text, -- 'vente', 'commande', 'ajustement', etc.
  user_id uuid REFERENCES users(id) NOT NULL,
  date_mouvement timestamptz DEFAULT now(),
  created_at timestamptz DEFAULT now()
);

-- Index pour les performances
CREATE INDEX IF NOT EXISTS idx_medicaments_code_barre ON medicaments(code_barre);
CREATE INDEX IF NOT EXISTS idx_medicaments_nom ON medicaments(nom);
CREATE INDEX IF NOT EXISTS idx_medicaments_categorie ON medicaments(categorie);
CREATE INDEX IF NOT EXISTS idx_medicaments_expiration ON medicaments(date_expiration);
CREATE INDEX IF NOT EXISTS idx_ventes_date ON ventes(date_vente);
CREATE INDEX IF NOT EXISTS idx_ventes_vendeur ON ventes(vendeur_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, lu);
CREATE INDEX IF NOT EXISTS idx_mouvements_medicament ON mouvements_stock(medicament_id);
CREATE INDEX IF NOT EXISTS idx_mouvements_date ON mouvements_stock(date_mouvement);

-- Activation RLS sur toutes les tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;
ALTER TABLE fournisseurs ENABLE ROW LEVEL SECURITY;
ALTER TABLE medicaments ENABLE ROW LEVEL SECURITY;
ALTER TABLE ventes ENABLE ROW LEVEL SECURITY;
ALTER TABLE details_vente ENABLE ROW LEVEL SECURITY;
ALTER TABLE commandes_fournisseur ENABLE ROW LEVEL SECURITY;
ALTER TABLE details_commande_fournisseur ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE mouvements_stock ENABLE ROW LEVEL SECURITY;

-- Politiques RLS pour users
CREATE POLICY "Users can read own profile" ON users
  FOR SELECT TO authenticated
  USING (auth.uid() = id);

CREATE POLICY "Users can update own profile" ON users
  FOR UPDATE TO authenticated
  USING (auth.uid() = id);

-- Politiques RLS pour clients (admin et vendeur)
CREATE POLICY "Admin and vendeur can manage clients" ON clients
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

-- Politiques RLS pour fournisseurs (admin seulement)
CREATE POLICY "Admin can manage fournisseurs" ON fournisseurs
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role = 'admin'
    )
  );

-- Politiques RLS pour médicaments
CREATE POLICY "All authenticated users can read medicaments" ON medicaments
  FOR SELECT TO authenticated
  USING (true);

CREATE POLICY "Admin and vendeur can manage medicaments" ON medicaments
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

-- Politiques RLS pour ventes
CREATE POLICY "All authenticated users can read ventes" ON ventes
  FOR SELECT TO authenticated
  USING (true);

CREATE POLICY "Admin and vendeur can create ventes" ON ventes
  FOR INSERT TO authenticated
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

CREATE POLICY "Admin can update ventes" ON ventes
  FOR UPDATE TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role = 'admin'
    )
  );

-- Politiques RLS pour details_vente
CREATE POLICY "All authenticated users can read details_vente" ON details_vente
  FOR SELECT TO authenticated
  USING (true);

CREATE POLICY "Admin and vendeur can manage details_vente" ON details_vente
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

-- Politiques RLS pour commandes_fournisseur (admin seulement)
CREATE POLICY "Admin can manage commandes_fournisseur" ON commandes_fournisseur
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role = 'admin'
    )
  );

-- Politiques RLS pour details_commande_fournisseur (admin seulement)
CREATE POLICY "Admin can manage details_commande_fournisseur" ON details_commande_fournisseur
  FOR ALL TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role = 'admin'
    )
  );

-- Politiques RLS pour notifications
CREATE POLICY "Users can read own notifications" ON notifications
  FOR SELECT TO authenticated
  USING (user_id = auth.uid() OR user_id IS NULL);

CREATE POLICY "System can create notifications" ON notifications
  FOR INSERT TO authenticated
  WITH CHECK (true);

CREATE POLICY "Users can update own notifications" ON notifications
  FOR UPDATE TO authenticated
  USING (user_id = auth.uid());

-- Politiques RLS pour mouvements_stock
CREATE POLICY "All authenticated users can read mouvements_stock" ON mouvements_stock
  FOR SELECT TO authenticated
  USING (true);

CREATE POLICY "Admin and vendeur can create mouvements_stock" ON mouvements_stock
  FOR INSERT TO authenticated
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM users 
      WHERE users.id = auth.uid() 
      AND users.role IN ('admin', 'vendeur')
    )
  );

-- Fonction pour générer un numéro de facture
CREATE OR REPLACE FUNCTION generate_facture_numero()
RETURNS text AS $$
DECLARE
  current_year text;
  sequence_num integer;
  facture_num text;
BEGIN
  current_year := EXTRACT(YEAR FROM NOW())::text;
  
  SELECT COALESCE(MAX(CAST(SUBSTRING(facture_numero FROM 6) AS integer)), 0) + 1
  INTO sequence_num
  FROM ventes
  WHERE facture_numero LIKE current_year || '-%';
  
  facture_num := current_year || '-' || LPAD(sequence_num::text, 6, '0');
  
  RETURN facture_num;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour générer un numéro de commande
CREATE OR REPLACE FUNCTION generate_commande_numero()
RETURNS text AS $$
DECLARE
  current_year text;
  sequence_num integer;
  commande_num text;
BEGIN
  current_year := EXTRACT(YEAR FROM NOW())::text;
  
  SELECT COALESCE(MAX(CAST(SUBSTRING(numero_commande FROM 5) AS integer)), 0) + 1
  INTO sequence_num
  FROM commandes_fournisseur
  WHERE numero_commande LIKE 'CMD' || current_year || '-%';
  
  commande_num := 'CMD' || current_year || '-' || LPAD(sequence_num::text, 4, '0');
  
  RETURN commande_num;
END;
$$ LANGUAGE plpgsql;

-- Trigger pour mettre à jour updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Application des triggers
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_clients_updated_at BEFORE UPDATE ON clients
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_fournisseurs_updated_at BEFORE UPDATE ON fournisseurs
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_medicaments_updated_at BEFORE UPDATE ON medicaments
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_commandes_updated_at BEFORE UPDATE ON commandes_fournisseur
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();