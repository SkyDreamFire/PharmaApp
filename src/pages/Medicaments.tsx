import React, { useState, useEffect } from 'react';
import { 
  Plus, 
  Search, 
  Filter, 
  Edit, 
  Trash2, 
  AlertTriangle, 
  Package, 
  Calendar,
  Barcode,
  DollarSign,
  Eye,
  Download
} from 'lucide-react';
import { supabase } from '../lib/supabase';
import { Medicament, Fournisseur } from '../types';
import { useAuth } from '../contexts/AuthContext';

const Medicaments: React.FC = () => {
  const { user } = useAuth();
  const [medicaments, setMedicaments] = useState<Medicament[]>([]);
  const [fournisseurs, setFournisseurs] = useState<Fournisseur[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingMedicament, setEditingMedicament] = useState<Medicament | null>(null);
  const [showDetails, setShowDetails] = useState<string | null>(null);

  const [formData, setFormData] = useState({
    nom: '',
    code_barre: '',
    categorie: '',
    forme: '',
    dosage: '',
    prix_achat: '',
    prix_vente: '',
    quantite_stock: '',
    seuil_alerte: '',
    date_expiration: '',
    fournisseur_id: '',
    prescription_requise: false
  });

  const categories = [
    'Antalgique', 'Anti-inflammatoire', 'Antibiotique', 'Antiacide',
    'Bronchodilatateur', 'Antidiarrhéique', 'Hormone thyroïdienne',
    'Cardiovasculaire', 'Dermatologie', 'Ophtalmologie', 'ORL'
  ];

  const formes = [
    'Comprimé', 'Gélule', 'Sirop', 'Suspension', 'Inhalateur',
    'Poudre', 'Crème', 'Pommade', 'Collyre', 'Spray'
  ];

  useEffect(() => {
    fetchMedicaments();
    fetchFournisseurs();
  }, []);

  const fetchMedicaments = async () => {
    try {
      const { data, error } = await supabase
        .from('medicaments')
        .select(`
          *,
          fournisseur:fournisseurs(*)
        `)
        .eq('actif', true)
        .order('nom');

      if (error) throw error;
      setMedicaments(data || []);
    } catch (error) {
      console.error('Error fetching medicaments:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchFournisseurs = async () => {
    try {
      const { data, error } = await supabase
        .from('fournisseurs')
        .select('*')
        .eq('actif', true)
        .order('nom');

      if (error) throw error;
      setFournisseurs(data || []);
    } catch (error) {
      console.error('Error fetching fournisseurs:', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const medicamentData = {
        ...formData,
        prix_achat: parseFloat(formData.prix_achat),
        prix_vente: parseFloat(formData.prix_vente),
        quantite_stock: parseInt(formData.quantite_stock),
        seuil_alerte: parseInt(formData.seuil_alerte),
        date_expiration: formData.date_expiration || null,
        fournisseur_id: formData.fournisseur_id || null
      };

      if (editingMedicament) {
        const { error } = await supabase
          .from('medicaments')
          .update(medicamentData)
          .eq('id', editingMedicament.id);

        if (error) throw error;

        // Enregistrer le mouvement de stock si la quantité a changé
        if (medicamentData.quantite_stock !== editingMedicament.quantite_stock) {
          await supabase.from('mouvements_stock').insert({
            medicament_id: editingMedicament.id,
            type: 'ajustement',
            quantite: medicamentData.quantite_stock - editingMedicament.quantite_stock,
            quantite_avant: editingMedicament.quantite_stock,
            quantite_apres: medicamentData.quantite_stock,
            motif: 'Ajustement manuel du stock',
            user_id: user?.id
          });
        }
      } else {
        const { data, error } = await supabase
          .from('medicaments')
          .insert(medicamentData)
          .select()
          .single();

        if (error) throw error;

        // Enregistrer le mouvement de stock initial
        if (medicamentData.quantite_stock > 0) {
          await supabase.from('mouvements_stock').insert({
            medicament_id: data.id,
            type: 'entree',
            quantite: medicamentData.quantite_stock,
            quantite_avant: 0,
            quantite_apres: medicamentData.quantite_stock,
            motif: 'Stock initial',
            user_id: user?.id
          });
        }
      }

      resetForm();
      fetchMedicaments();
    } catch (error) {
      console.error('Error saving medicament:', error);
      alert('Erreur lors de la sauvegarde');
    }
  };

  const handleEdit = (medicament: Medicament) => {
    setEditingMedicament(medicament);
    setFormData({
      nom: medicament.nom,
      code_barre: medicament.code_barre || '',
      categorie: medicament.categorie,
      forme: medicament.forme,
      dosage: medicament.dosage || '',
      prix_achat: medicament.prix_achat.toString(),
      prix_vente: medicament.prix_vente.toString(),
      quantite_stock: medicament.quantite_stock.toString(),
      seuil_alerte: medicament.seuil_alerte.toString(),
      date_expiration: medicament.date_expiration || '',
      fournisseur_id: medicament.fournisseur_id || '',
      prescription_requise: medicament.prescription_requise || false
    });
    setShowModal(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce médicament ?')) return;

    try {
      const { error } = await supabase
        .from('medicaments')
        .update({ actif: false })
        .eq('id', id);

      if (error) throw error;
      fetchMedicaments();
    } catch (error) {
      console.error('Error deleting medicament:', error);
      alert('Erreur lors de la suppression');
    }
  };

  const resetForm = () => {
    setFormData({
      nom: '',
      code_barre: '',
      categorie: '',
      forme: '',
      dosage: '',
      prix_achat: '',
      prix_vente: '',
      quantite_stock: '',
      seuil_alerte: '',
      date_expiration: '',
      fournisseur_id: '',
      prescription_requise: false
    });
    setEditingMedicament(null);
    setShowModal(false);
  };

  const filteredMedicaments = medicaments.filter(med => {
    const matchesSearch = med.nom.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         med.code_barre?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = !selectedCategory || med.categorie === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const getStockStatus = (medicament: Medicament) => {
    if (medicament.quantite_stock === 0) {
      return { status: 'rupture', color: 'text-red-600 bg-red-50', label: 'Rupture' };
    } else if (medicament.quantite_stock <= medicament.seuil_alerte) {
      return { status: 'faible', color: 'text-orange-600 bg-orange-50', label: 'Stock faible' };
    } else {
      return { status: 'normal', color: 'text-green-600 bg-green-50', label: 'En stock' };
    }
  };

  const getExpirationStatus = (dateExpiration: string | null) => {
    if (!dateExpiration) return null;
    
    const today = new Date();
    const expDate = new Date(dateExpiration);
    const diffTime = expDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
      return { color: 'text-red-600', label: 'Expiré' };
    } else if (diffDays <= 30) {
      return { color: 'text-orange-600', label: `Expire dans ${diffDays} jours` };
    } else if (diffDays <= 90) {
      return { color: 'text-yellow-600', label: `Expire dans ${diffDays} jours` };
    }
    return null;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">Gestion des Médicaments</h1>
          <p className="text-gray-600 mt-1">
            {medicaments.length} médicaments • {medicaments.filter(m => m.quantite_stock <= m.seuil_alerte).length} alertes stock
          </p>
        </div>
        {user?.role !== 'comptable' && (
          <button
            onClick={() => setShowModal(true)}
            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2"
          >
            <Plus className="w-5 h-5" />
            <span>Nouveau médicament</span>
          </button>
        )}
      </div>

      {/* Filtres et recherche */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="relative">
            <Search className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
            <input
              type="text"
              placeholder="Rechercher par nom ou code-barre..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          
          <div className="relative">
            <Filter className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
            >
              <option value="">Toutes les catégories</option>
              {categories.map(cat => (
                <option key={cat} value={cat}>{cat}</option>
              ))}
            </select>
          </div>

          <div className="flex space-x-2">
            <button className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
              <Download className="w-4 h-4" />
              <span>Exporter</span>
            </button>
          </div>
        </div>
      </div>

      {/* Statistiques rapides */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Total médicaments</p>
              <p className="text-2xl font-bold text-gray-800">{medicaments.length}</p>
            </div>
            <Package className="w-8 h-8 text-blue-500" />
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Stock faible</p>
              <p className="text-2xl font-bold text-orange-600">
                {medicaments.filter(m => m.quantite_stock <= m.seuil_alerte && m.quantite_stock > 0).length}
              </p>
            </div>
            <AlertTriangle className="w-8 h-8 text-orange-500" />
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Rupture de stock</p>
              <p className="text-2xl font-bold text-red-600">
                {medicaments.filter(m => m.quantite_stock === 0).length}
              </p>
            </div>
            <AlertTriangle className="w-8 h-8 text-red-500" />
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Expiration proche</p>
              <p className="text-2xl font-bold text-yellow-600">
                {medicaments.filter(m => {
                  if (!m.date_expiration) return false;
                  const diffDays = Math.ceil((new Date(m.date_expiration).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));
                  return diffDays <= 90 && diffDays > 0;
                }).length}
              </p>
            </div>
            <Calendar className="w-8 h-8 text-yellow-500" />
          </div>
        </div>
      </div>

      {/* Liste des médicaments */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-800">Liste des médicaments</h2>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Médicament
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Stock
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Prix
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Expiration
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Fournisseur
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredMedicaments.map((medicament) => {
                const stockStatus = getStockStatus(medicament);
                const expirationStatus = getExpirationStatus(medicament.date_expiration);
                
                return (
                  <tr key={medicament.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                          <Package className="w-5 h-5 text-blue-600" />
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-gray-900">
                            {medicament.nom}
                            {medicament.prescription_requise && (
                              <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Prescription
                              </span>
                            )}
                          </div>
                          <div className="text-sm text-gray-500">
                            {medicament.forme} • {medicament.dosage}
                          </div>
                          {medicament.code_barre && (
                            <div className="text-xs text-gray-400 flex items-center mt-1">
                              <Barcode className="w-3 h-3 mr-1" />
                              {medicament.code_barre}
                            </div>
                          )}
                        </div>
                      </div>
                    </td>
                    
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${stockStatus.color}`}>
                          {stockStatus.label}
                        </span>
                        <span className="ml-2 text-sm text-gray-900">
                          {medicament.quantite_stock}
                        </span>
                      </div>
                      <div className="text-xs text-gray-500">
                        Seuil: {medicament.seuil_alerte}
                      </div>
                    </td>
                    
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900 flex items-center">
                        <DollarSign className="w-4 h-4 mr-1 text-gray-400" />
                        {medicament.prix_vente.toFixed(2)}€
                      </div>
                      <div className="text-xs text-gray-500">
                        Achat: {medicament.prix_achat.toFixed(2)}€
                      </div>
                    </td>
                    
                    <td className="px-6 py-4 whitespace-nowrap">
                      {medicament.date_expiration ? (
                        <div>
                          <div className="text-sm text-gray-900">
                            {new Date(medicament.date_expiration).toLocaleDateString('fr-FR')}
                          </div>
                          {expirationStatus && (
                            <div className={`text-xs ${expirationStatus.color}`}>
                              {expirationStatus.label}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="text-sm text-gray-400">Non définie</span>
                      )}
                    </td>
                    
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {medicament.fournisseur?.nom || 'Non défini'}
                      </div>
                    </td>
                    
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        <button
                          onClick={() => setShowDetails(showDetails === medicament.id ? null : medicament.id)}
                          className="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                          title="Voir détails"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        
                        {user?.role !== 'comptable' && (
                          <>
                            <button
                              onClick={() => handleEdit(medicament)}
                              className="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50"
                              title="Modifier"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
                            
                            <button
                              onClick={() => handleDelete(medicament.id)}
                              className="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50"
                              title="Supprimer"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal d'ajout/modification */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-gray-200">
              <h2 className="text-xl font-semibold text-gray-800">
                {editingMedicament ? 'Modifier le médicament' : 'Nouveau médicament'}
              </h2>
            </div>
            
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nom du médicament *
                  </label>
                  <input
                    type="text"
                    value={formData.nom}
                    onChange={(e) => setFormData({ ...formData, nom: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Code-barre
                  </label>
                  <input
                    type="text"
                    value={formData.code_barre}
                    onChange={(e) => setFormData({ ...formData, code_barre: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Catégorie *
                  </label>
                  <select
                    value={formData.categorie}
                    onChange={(e) => setFormData({ ...formData, categorie: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  >
                    <option value="">Sélectionner une catégorie</option>
                    {categories.map(cat => (
                      <option key={cat} value={cat}>{cat}</option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Forme *
                  </label>
                  <select
                    value={formData.forme}
                    onChange={(e) => setFormData({ ...formData, forme: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  >
                    <option value="">Sélectionner une forme</option>
                    {formes.map(forme => (
                      <option key={forme} value={forme}>{forme}</option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Dosage
                  </label>
                  <input
                    type="text"
                    value={formData.dosage}
                    onChange={(e) => setFormData({ ...formData, dosage: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="ex: 500mg"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Fournisseur
                  </label>
                  <select
                    value={formData.fournisseur_id}
                    onChange={(e) => setFormData({ ...formData, fournisseur_id: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    <option value="">Sélectionner un fournisseur</option>
                    {fournisseurs.map(fournisseur => (
                      <option key={fournisseur.id} value={fournisseur.id}>
                        {fournisseur.nom}
                      </option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Prix d'achat (€) *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.prix_achat}
                    onChange={(e) => setFormData({ ...formData, prix_achat: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Prix de vente (€) *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.prix_vente}
                    onChange={(e) => setFormData({ ...formData, prix_vente: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Quantité en stock *
                  </label>
                  <input
                    type="number"
                    value={formData.quantite_stock}
                    onChange={(e) => setFormData({ ...formData, quantite_stock: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Seuil d'alerte *
                  </label>
                  <input
                    type="number"
                    value={formData.seuil_alerte}
                    onChange={(e) => setFormData({ ...formData, seuil_alerte: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Date d'expiration
                  </label>
                  <input
                    type="date"
                    value={formData.date_expiration}
                    onChange={(e) => setFormData({ ...formData, date_expiration: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
              </div>
              
              <div className="flex items-center">
                <input
                  type="checkbox"
                  id="prescription_requise"
                  checked={formData.prescription_requise}
                  onChange={(e) => setFormData({ ...formData, prescription_requise: e.target.checked })}
                  className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label htmlFor="prescription_requise" className="ml-2 block text-sm text-gray-700">
                  Prescription médicale requise
                </label>
              </div>
              
              <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  onClick={resetForm}
                  className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
                >
                  {editingMedicament ? 'Modifier' : 'Ajouter'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Medicaments;