import React from 'react';
import { NavLink } from 'react-router-dom';
import { 
  LayoutDashboard, 
  Pill, 
  ShoppingCart, 
  Users, 
  Truck, 
  Package, 
  Bell, 
  BarChart3,
  LogOut,
  Stethoscope
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';

const Sidebar: React.FC = () => {
  const { user, signOut } = useAuth();

  const menuItems = [
    { icon: LayoutDashboard, label: 'Dashboard', path: '/dashboard', roles: ['admin', 'vendeur', 'comptable'] },
    { icon: Pill, label: 'Médicaments', path: '/medicaments', roles: ['admin', 'vendeur'] },
    { icon: ShoppingCart, label: 'Ventes', path: '/ventes', roles: ['admin', 'vendeur'] },
    { icon: Users, label: 'Clients', path: '/clients', roles: ['admin', 'vendeur'] },
    { icon: Truck, label: 'Fournisseurs', path: '/fournisseurs', roles: ['admin'] },
    { icon: Package, label: 'Commandes', path: '/commandes', roles: ['admin'] },
    { icon: Bell, label: 'Notifications', path: '/notifications', roles: ['admin', 'vendeur'] },
    { icon: BarChart3, label: 'Rapports', path: '/rapports', roles: ['admin', 'comptable'] },
  ];

  const filteredMenuItems = menuItems.filter(item => 
    user && item.roles.includes(user.role)
  );

  return (
    <div className="bg-white shadow-lg h-screen w-64 fixed left-0 top-0 z-30">
      <div className="p-6 border-b border-gray-200">
        <div className="flex items-center space-x-3">
          <div className="bg-blue-600 p-2 rounded-lg">
            <Stethoscope className="w-6 h-6 text-white" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-gray-800">PharmaCare</h1>
            <p className="text-sm text-gray-500">Gestion Pharmacie</p>
          </div>
        </div>
      </div>

      <nav className="mt-6">
        <div className="px-4 space-y-2">
          {filteredMenuItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              className={({ isActive }) =>
                `flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors duration-200 ${
                  isActive
                    ? 'bg-blue-50 text-blue-600 border-r-2 border-blue-600'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'
                }`
              }
            >
              <item.icon className="w-5 h-5" />
              <span className="font-medium">{item.label}</span>
            </NavLink>
          ))}
        </div>
      </nav>

      <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
        <div className="flex items-center space-x-3 mb-4">
          <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
            <span className="text-blue-600 font-semibold">
              {user?.prenom?.[0]}{user?.nom?.[0]}
            </span>
          </div>
          <div className="flex-1">
            <p className="text-sm font-medium text-gray-800">
              {user?.prenom} {user?.nom}
            </p>
            <p className="text-xs text-gray-500 capitalize">{user?.role}</p>
          </div>
        </div>
        <button
          onClick={signOut}
          className="flex items-center space-x-2 w-full px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200"
        >
          <LogOut className="w-4 h-4" />
          <span className="text-sm font-medium">Déconnexion</span>
        </button>
      </div>
    </div>
  );
};

export default Sidebar;