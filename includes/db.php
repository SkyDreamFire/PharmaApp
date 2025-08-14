<?php
/**
 * Classe de connexion à la base de données
 * Utilise le pattern Singleton pour garantir une seule connexion
 */

class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    // Paramètres de connexion — à externaliser dans un fichier de config sécurisé en production
    private string $host = 'localhost';
    private string $dbname = 'pharma_db';
    private string $username = 'root';
    private string $password = '';

    /**
     * Constructeur privé pour le Singleton
     */
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // En production, remplacer die() par un log sécurisé
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    /**
     * Méthode statique d'accès à l'instance unique
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne l'objet PDO brut si besoin
     */
    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Exécute une requête SELECT qui retourne un seul enregistrement
     */
    public function selectOne(string $sql, array $params = []): ?array {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            error_log("Erreur SELECT ONE : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Exécute une requête SELECT qui retourne plusieurs enregistrements
     */
    public function select(string $sql, array $params = []): array {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur SELECT : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Exécute une requête INSERT/UPDATE/DELETE
     */
    public function execute(string $sql, array $params = []): bool {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur EXECUTE : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mise à jour de données via une requête préparée
     */
    public function update(string $sql, array $params = []): bool {
        return $this->execute($sql, $params);
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré
     */
    public function lastInsertId(): string {
        return $this->connection->lastInsertId();
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): bool {
        return $this->connection->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback(): bool {
        return $this->connection->rollBack();
    }
}
