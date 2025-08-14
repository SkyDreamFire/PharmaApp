import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout/Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Medicaments from './pages/Medicaments';

function App() {
  return (
    <AuthProvider>
      <Router>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route
            path="/*"
            element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }
          >
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="medicaments" element={
              <ProtectedRoute allowedRoles={['admin', 'vendeur']}>
                <Medicaments />
              </ProtectedRoute>
            } />
            <Route path="ventes" element={<div className="p-6">Module Ventes (En développement)</div>} />
            <Route path="clients" element={<div className="p-6">Module Clients (En développement)</div>} />
            <Route path="fournisseurs" element={<div className="p-6">Module Fournisseurs (En développement)</div>} />
            <Route path="commandes" element={<div className="p-6">Module Commandes (En développement)</div>} />
            <Route path="notifications" element={<div className="p-6">Module Notifications (En développement)</div>} />
            <Route path="rapports" element={<div className="p-6">Module Rapports (En développement)</div>} />
          </Route>
        </Routes>
      </Router>
    </AuthProvider>
  );
}

export default App;