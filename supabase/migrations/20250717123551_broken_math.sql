/*
  # Données d'exemple pour le système de pharmacie

  1. Utilisateurs de démonstration
  2. Fournisseurs d'exemple
  3. Médicaments avec stock
  4. Clients d'exemple
  5. Notifications d'exemple
*/

-- Insertion des fournisseurs d'exemple
INSERT INTO fournisseurs (id, nom, contact, telephone, email, adresse) VALUES
  ('f1234567-1234-1234-1234-123456789012', 'Laboratoires Sanofi', 'Marie Dubois', '+33 1 23 45 67 89', 'contact@sanofi.fr', '54 rue La Boétie, 75008 Paris'),
  ('f2234567-1234-1234-1234-123456789012', 'Pfizer France', 'Jean Martin', '+33 1 34 56 78 90', 'commandes@pfizer.fr', '23-25 avenue du Dr Lannelongue, 75014 Paris'),
  ('f3234567-1234-1234-1234-123456789012', 'Laboratoires Roche', 'Sophie Laurent', '+33 1 45 67 89 01', 'orders@roche.fr', '30 cours de l''Île Seguin, 92100 Boulogne-Billancourt'),
  ('f4234567-1234-1234-1234-123456789012', 'Novartis Pharma', 'Pierre Moreau', '+33 1 56 78 90 12', 'supply@novartis.fr', '8-10 rue Henri Sainte-Claire Deville, 92500 Rueil-Malmaison');

-- Insertion des médicaments d'exemple
INSERT INTO medicaments (nom, code_barre, categorie, forme, dosage, prix_achat, prix_vente, quantite_stock, seuil_alerte, date_expiration, fournisseur_id, prescription_requise) VALUES
  ('Paracétamol', '3401234567890', 'Antalgique', 'Comprimé', '500mg', 2.50, 4.20, 150, 20, '2025-12-31', 'f1234567-1234-1234-1234-123456789012', false),
  ('Ibuprofène', '3401234567891', 'Anti-inflammatoire', 'Comprimé', '400mg', 3.20, 5.80, 80, 15, '2025-10-15', 'f1234567-1234-1234-1234-123456789012', false),
  ('Aspirine', '3401234567892', 'Antalgique', 'Comprimé', '100mg', 1.80, 3.50, 200, 25, '2026-03-20', 'f2234567-1234-1234-1234-123456789012', false),
  ('Amoxicilline', '3401234567893', 'Antibiotique', 'Gélule', '500mg', 8.50, 15.20, 45, 10, '2025-08-10', 'f2234567-1234-1234-1234-123456789012', true),
  ('Doliprane', '3401234567894', 'Antalgique', 'Sirop', '100ml', 4.20, 7.80, 60, 12, '2025-11-25', 'f1234567-1234-1234-1234-123456789012', false),
  ('Ventoline', '3401234567895', 'Bronchodilatateur', 'Inhalateur', '100mcg', 12.50, 22.40, 25, 8, '2025-09-30', 'f3234567-1234-1234-1234-123456789012', true),
  ('Smecta', '3401234567896', 'Antidiarrhéique', 'Poudre', '3g', 3.80, 6.90, 90, 15, '2026-01-15', 'f3234567-1234-1234-1234-123456789012', false),
  ('Levothyrox', '3401234567897', 'Hormone thyroïdienne', 'Comprimé', '50mcg', 6.20, 11.50, 35, 10, '2025-07-20', 'f4234567-1234-1234-1234-123456789012', true),
  ('Gaviscon', '3401234567898', 'Antiacide', 'Suspension', '150ml', 5.40, 9.20, 70, 12, '2025-12-05', 'f4234567-1234-1234-1234-123456789012', false),
  ('Nurofen', '3401234567899', 'Anti-inflammatoire', 'Comprimé', '200mg', 4.10, 7.30, 55, 15, '2025-10-30', 'f1234567-1234-1234-1234-123456789012', false);

-- Insertion des clients d'exemple
INSERT INTO clients (nom, prenom, telephone, email, adresse, date_naissance, points_fidelite) VALUES
  ('Dupont', 'Marie', '0123456789', 'marie.dupont@email.com', '15 rue de la Paix, 75001 Paris', '1985-03-15', 120),
  ('Martin', 'Jean', '0234567890', 'jean.martin@email.com', '28 avenue des Champs, 75008 Paris', '1978-07-22', 85),
  ('Bernard', 'Sophie', '0345678901', 'sophie.bernard@email.com', '42 boulevard Saint-Germain, 75005 Paris', '1992-11-08', 200),
  ('Petit', 'Pierre', '0456789012', 'pierre.petit@email.com', '7 place Vendôme, 75001 Paris', '1965-12-03', 45),
  ('Durand', 'Claire', '0567890123', 'claire.durand@email.com', '33 rue de Rivoli, 75004 Paris', '1988-05-17', 160);

-- Insertion de notifications d'exemple pour les stocks faibles
INSERT INTO notifications (type, titre, message, medicament_id) 
SELECT 
  'stock_faible',
  'Stock faible: ' || nom,
  'Le médicament ' || nom || ' a un stock de ' || quantite_stock || ' unités (seuil: ' || seuil_alerte || ')',
  id
FROM medicaments 
WHERE quantite_stock <= seuil_alerte;

-- Insertion de notifications pour les médicaments proches de l'expiration
INSERT INTO notifications (type, titre, message, medicament_id)
SELECT 
  'expiration',
  'Expiration proche: ' || nom,
  'Le médicament ' || nom || ' expire le ' || date_expiration::text,
  id
FROM medicaments 
WHERE date_expiration <= CURRENT_DATE + INTERVAL '3 months';