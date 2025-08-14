import React, { useState, useEffect } from 'react';
import { 
  TrendingUp, 
  Package, 
  Users, 
  AlertTriangle, 
  DollarSign,
  ShoppingCart,
  Calendar,
  Activity
} from 'lucide-react';
import { supabase } from '../lib/supabase';

interface DashboardStats {
  ventesJour: number;
  ventesSemaine: number;
  ventesMois: number;
  totalMedicaments: number;
  stockFaible: number;
  totalClients: number;
  commandesEnAttente: number;
}

const Dashboard: React.FC = () => {
  const [stats, setStats] = useState<DashboardStats>({
    ventesJour: 0,
    ventesSemaine: 0,
    ventesMois: 0,
    totalMedicaments: 0,
    stockFaible: 0,
    totalClients: 0,
    commandesEnAttente: 0,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardStats();
  }, []);

  const fetchDashboardStats = async () => {
    try {
      const today = new Date().toISOString().split('T')[0];
      const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
      const monthAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

      // Ventes du jour
      const { data: ventesJour } = await supabase
        .from('ventes')
        .select('total')
        .gte('date_vente', today);

      // Ventes de la semaine
      const { data: ventesSemaine } = await supabase
        .from('ventes')
        .select('total')
        .gte('date_vente', weekAgo);

      // Ventes du mois
      const { data: ventesMois } = await supabase
        .from('ventes')
        .select('total')
        .gte('date_vente', monthAgo);

      // Total médicaments
      const { count: totalMedicaments } = await supabase
        .from('medicaments')
        .select('*', { count: 'exact', head: true });

      // Stock faible
      const { data: stockFaible } = await supabase
        .from('medicaments')
        .select('quantite_stock, seuil_alerte')
        .lt('quantite_stock', 'seuil_alerte');

      // Total clients
      const { count: totalClients } = await supabase
        .from('clients')
        .select('*', { count: 'exact', head: true });

      // Commandes en attente
      const { count: commandesEnAttente } = await supabase
        .from('commandes_fournisseur')
        .select('*', { count: 'exact', head: true })
        .eq('statut', 'en_attente');

      setStats({
        ventesJour: ventesJour?.reduce((sum, v) => sum + v.total, 0) || 0,
        ventesSemaine: ventesSemaine?.reduce((sum, v) => sum + v.total, 0) || 0,
        ventesMois: ventesMois?.reduce((sum, v) => sum + v.total, 0) || 0,
        totalMedicaments: totalMedicaments || 0,
        stockFaible: stockFaible?.length || 0,
        totalClients: totalClients || 0,
        commandesEnAttente: commandesEnAttente || 0,
      });
    } catch (error) {
      console.error('Error fetching dashboard stats:', error);
    } finally {
      setLoading(false);
    }
  };

  const statCards = [
    {
      title: 'Ventes du jour',
      value: `${stats.ventesJour.toFixed(2)} €`,
      icon: DollarSign,
      color: 'bg-green-500',
      change: '+12%',
    },
    {
      title: 'Ventes de la semaine',
      value: `${stats.ventesSemaine.toFixed(2)} €`,
      icon: TrendingUp,
      color: 'bg-blue-500',
      change: '+8%',
    },
    {
      title: 'Médicaments en stock',
      value: stats.totalMedicaments.toString(),
      icon: Package,
      color: 'bg-purple-500',
      change: '+3',
    },
    {
      title: 'Stock faible',
      value: stats.stockFaible.toString(),
      icon: AlertTriangle,
      color: 'bg-orange-500',
      change: stats.stockFaible > 0 ? 'Attention' : 'OK',
    },
    {
      title: 'Total clients',
      value: stats.totalClients.toString(),
      icon: Users,
      color: 'bg-indigo-500',
      change: '+5',
    },
    {
      title: 'Commandes en attente',
      value: stats.commandesEnAttente.toString(),
      icon: ShoppingCart,
      color: 'bg-red-500',
      change: stats.commandesEnAttente > 0 ? 'À traiter' : 'OK',
    },
  ];

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
        <h1 className="text-3xl font-bold text-gray-800">Tableau de bord</h1>
        <div className="flex items-center space-x-2 text-gray-600">
          <Calendar className="w-5 h-5" />
          <span>{new Date().toLocaleDateString('fr-FR', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
          })}</span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {statCards.map((card, index) => (
          <div key={index} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600 mb-1">{card.title}</p>
                <p className="text-2xl font-bold text-gray-800">{card.value}</p>
                <p className="text-sm text-gray-500 mt-1">{card.change}</p>
              </div>
              <div className={`${card.color} p-3 rounded-lg`}>
                <card.icon className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-gray-800">Activité récente</h2>
            <Activity className="w-5 h-5 text-gray-500" />
          </div>
          <div className="space-y-4">
            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
              <div className="w-2 h-2 bg-green-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-800">Nouvelle vente enregistrée</p>
                <p className="text-xs text-gray-500">Il y a 5 minutes</p>
              </div>
            </div>
            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
              <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-800">Stock mis à jour</p>
                <p className="text-xs text-gray-500">Il y a 15 minutes</p>
              </div>
            </div>
            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
              <div className="w-2 h-2 bg-orange-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-800">Alerte stock faible</p>
                <p className="text-xs text-gray-500">Il y a 1 heure</p>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-gray-800">Médicaments populaires</h2>
            <TrendingUp className="w-5 h-5 text-gray-500" />
          </div>
          <div className="space-y-4">
            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p className="text-sm font-medium text-gray-800">Paracétamol 500mg</p>
                <p className="text-xs text-gray-500">45 ventes cette semaine</p>
              </div>
              <span className="text-sm font-semibold text-green-600">+15%</span>
            </div>
            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p className="text-sm font-medium text-gray-800">Ibuprofène 400mg</p>
                <p className="text-xs text-gray-500">32 ventes cette semaine</p>
              </div>
              <span className="text-sm font-semibold text-green-600">+8%</span>
            </div>
            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p className="text-sm font-medium text-gray-800">Aspirine 100mg</p>
                <p className="text-xs text-gray-500">28 ventes cette semaine</p>
              </div>
              <span className="text-sm font-semibold text-green-600">+12%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;