export interface User {
  id: string;
  email: string;
  role: 'admin' | 'vendeur' | 'comptable';
  nom: string;
  prenom: string;
  created_at: string;
}

export interface Medicament {
  id: string;
  nom: string;
  code_barre: string;
  categorie: string;
  forme: string;
  prix_achat: number;
  prix_vente: number;
  quantite_stock: number;
  seuil_alerte: number;
  date_expiration: string;
  fournisseur_id: string;
  fournisseur?: Fournisseur;
  created_at: string;
  updated_at: string;
}

export interface Client {
  id: string;
  nom: string;
  prenom: string;
  telephone: string;
  email?: string;
  adresse?: string;
  date_naissance?: string;
  created_at: string;
}

export interface Fournisseur {
  id: string;
  nom: string;
  contact: string;
  telephone: string;
  email?: string;
  adresse?: string;
  created_at: string;
}

export interface Vente {
  id: string;
  client_id?: string;
  client?: Client;
  total: number;
  date_vente: string;
  vendeur_id: string;
  vendeur?: User;
  details_vente?: DetailVente[];
  facture_numero?: string;
}

export interface DetailVente {
  id: string;
  vente_id: string;
  medicament_id: string;
  medicament?: Medicament;
  quantite: number;
  prix_unitaire: number;
  sous_total: number;
}

export interface CommandeFournisseur {
  id: string;
  fournisseur_id: string;
  fournisseur?: Fournisseur;
  date_commande: string;
  date_livraison?: string;
  statut: 'en_attente' | 'livree' | 'annulee';
  total: number;
  details_commande?: DetailCommandeFournisseur[];
}

export interface DetailCommandeFournisseur {
  id: string;
  commande_id: string;
  medicament_id: string;
  medicament?: Medicament;
  quantite: number;
  prix_unitaire: number;
  sous_total: number;
}

export interface Notification {
  id: string;
  type: 'stock_faible' | 'expiration' | 'commande';
  titre: string;
  message: string;
  lu: boolean;
  created_at: string;
  medicament_id?: string;
  commande_id?: string;
}

export interface MouvementStock {
  id: string;
  medicament_id: string;
  medicament?: Medicament;
  type: 'entree' | 'sortie';
  quantite: number;
  motif: string;
  date_mouvement: string;
  user_id: string;
  user?: User;
}