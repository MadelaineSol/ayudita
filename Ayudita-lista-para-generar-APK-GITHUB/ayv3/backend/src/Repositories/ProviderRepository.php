<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Paginator;

/**
 * Perfiles de prestadores: búsqueda con filtros, geolocalización y CRUD del propio perfil.
 */
final class ProviderRepository extends BaseRepository
{
    private const BASE_SELECT = "
        SELECT pp.id, pp.user_id, u.name, u.avatar_url, u.city, u.lat, u.lng,
               pp.bio, pp.experience_years, pp.rate_hour, pp.rate_day, pp.radius_km,
               pp.available, pp.verified, pp.rating_avg, pp.rating_count, pp.jobs_done
          FROM provider_profiles pp
          JOIN users u ON u.id = pp.user_id
         WHERE pp.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status = 'active'";

    /**
     * Búsqueda con filtros: categoría, texto, precio, calificación,
     * experiencia, disponibilidad y distancia (fórmula de Haversine).
     */
    public function search(array $filters, Paginator $paginator): array
    {
        $where = [];
        $params = [];
        $select = self::BASE_SELECT;
        $having = '';
        $order = 'pp.rating_avg DESC, pp.jobs_done DESC';

        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM provider_categories pc
                                 WHERE pc.provider_id = pp.id AND pc.category_id = :category_id)';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(u.name LIKE :q OR pp.bio LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = $params['q'];
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'pp.rate_hour >= :min_price';
            $params['min_price'] = (float) $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'pp.rate_hour <= :max_price';
            $params['max_price'] = (float) $filters['max_price'];
        }
        if (!empty($filters['min_rating'])) {
            $where[] = 'pp.rating_avg >= :min_rating';
            $params['min_rating'] = (float) $filters['min_rating'];
        }
        if (!empty($filters['min_experience'])) {
            $where[] = 'pp.experience_years >= :min_experience';
            $params['min_experience'] = (int) $filters['min_experience'];
        }
        if (isset($filters['available']) && $filters['available'] !== '') {
            $where[] = 'pp.available = :available';
            $params['available'] = (int) (bool) $filters['available'];
        }

        // Distancia con Haversine si el cliente envía su ubicación
        if (!empty($filters['lat']) && !empty($filters['lng'])) {
            $select .= ', (6371 * ACOS(LEAST(1, COS(RADIANS(:lat)) * COS(RADIANS(u.lat))
                        * COS(RADIANS(u.lng) - RADIANS(:lng))
                        + SIN(RADIANS(:lat2)) * SIN(RADIANS(u.lat))))) AS distance_km';
            $params['lat'] = (float) $filters['lat'];
            $params['lat2'] = (float) $filters['lat'];
            $params['lng'] = (float) $filters['lng'];
            $where[] = 'u.lat IS NOT NULL AND u.lng IS NOT NULL';
            if (!empty($filters['radius'])) {
                $having = ' HAVING distance_km <= ' . (float) $filters['radius'];
            }
            if (($filters['sort'] ?? '') === 'distance') {
                $order = 'distance_km ASC';
            }
        }

        $order = match ($filters['sort'] ?? '') {
            'price_asc'  => 'pp.rate_hour ASC',
            'price_desc' => 'pp.rate_hour DESC',
            'rating'     => 'pp.rating_avg DESC',
            default      => $order,
        };

        $whereSql = $where === [] ? '' : ' AND ' . implode(' AND ', $where);
        $sql = $select . $whereSql . $having
             . " ORDER BY $order LIMIT {$paginator->perPage} OFFSET {$paginator->offset}";

        $items = $this->fetchAll($sql, $params);

        $countSql = 'SELECT COUNT(*) AS total FROM provider_profiles pp
                       JOIN users u ON u.id = pp.user_id
                      WHERE pp.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status = \'active\''
                  . $whereSql;
        $countParams = $params;
        unset($countParams['lat'], $countParams['lat2'], $countParams['lng']);
        if ($having !== '') {
            // Con filtro de radio el total exacto requiere subconsulta; aproximamos con los ítems.
            $total = count($items);
        } else {
            $total = (int) ($this->fetchOne($countSql, $countParams)['total'] ?? 0);
        }

        return ['items' => array_map([$this, 'castRow'], $items), 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $row = $this->fetchOne(self::BASE_SELECT . ' AND pp.id = ?', [$id]);
        return $row !== null ? $this->castRow($row) : null;
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM provider_profiles WHERE user_id = ? AND deleted_at IS NULL',
            [$userId]
        );
    }

    public function create(int $userId, array $data): int
    {
        return $this->insert('provider_profiles', array_merge(['user_id' => $userId], $data));
    }

    public function updateByUserId(int $userId, array $data): void
    {
        $this->update('provider_profiles', $data, 'user_id = :_uid', ['_uid' => $userId]);
    }

    public function setCategories(int $providerId, array $categoryIds): void
    {
        $this->execute('DELETE FROM provider_categories WHERE provider_id = ?', [$providerId]);
        $stmt = $this->db->prepare('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            $stmt->execute([$providerId, $categoryId]);
        }
    }

    public function categories(int $providerId): array
    {
        return $this->fetchAll(
            'SELECT c.id, c.name, c.icon, c.slug FROM provider_categories pc
              JOIN categories c ON c.id = pc.category_id
             WHERE pc.provider_id = ?',
            [$providerId]
        );
    }

    public function photos(int $providerId): array
    {
        return $this->fetchAll(
            'SELECT id, url, sort_order FROM provider_photos WHERE provider_id = ? ORDER BY sort_order',
            [$providerId]
        );
    }

    public function addPhoto(int $providerId, string $url): int
    {
        return $this->insert('provider_photos', ['provider_id' => $providerId, 'url' => $url]);
    }

    public function certificates(int $providerId): array
    {
        return $this->fetchAll(
            'SELECT id, title, url, verified FROM provider_certificates WHERE provider_id = ?',
            [$providerId]
        );
    }

    public function addCertificate(int $providerId, string $title, ?string $url): int
    {
        return $this->insert('provider_certificates', [
            'provider_id' => $providerId,
            'title'       => $title,
            'url'         => $url,
        ]);
    }

    public function availability(int $providerId): array
    {
        return $this->fetchAll(
            'SELECT weekday, from_time, to_time FROM provider_availability WHERE provider_id = ? ORDER BY weekday',
            [$providerId]
        );
    }

    public function setAvailability(int $providerId, array $slots): void
    {
        $this->execute('DELETE FROM provider_availability WHERE provider_id = ?', [$providerId]);
        $stmt = $this->db->prepare(
            'INSERT INTO provider_availability (provider_id, weekday, from_time, to_time) VALUES (?, ?, ?, ?)'
        );
        foreach ($slots as $slot) {
            $stmt->execute([
                $providerId,
                (int) $slot['weekday'],
                $slot['from_time'],
                $slot['to_time'],
            ]);
        }
    }

    public function adjustBalance(int $providerId, float $delta): void
    {
        $this->execute(
            'UPDATE provider_profiles SET balance = balance + ? WHERE id = ?',
            [$delta, $providerId]
        );
    }

    private function castRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['rate_hour'] = (float) $row['rate_hour'];
        $row['rate_day'] = (float) $row['rate_day'];
        $row['rating_avg'] = (float) $row['rating_avg'];
        $row['rating_count'] = (int) $row['rating_count'];
        $row['jobs_done'] = (int) $row['jobs_done'];
        $row['experience_years'] = (int) $row['experience_years'];
        $row['available'] = (bool) $row['available'];
        $row['verified'] = (bool) $row['verified'];
        $row['lat'] = $row['lat'] !== null ? (float) $row['lat'] : null;
        $row['lng'] = $row['lng'] !== null ? (float) $row['lng'] : null;
        if (isset($row['distance_km'])) {
            $row['distance_km'] = round((float) $row['distance_km'], 1);
        }
        return $row;
    }
}
