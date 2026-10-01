<?php

namespace App\Model;

use CodeIgniter\Model;

/**
 * Key/value site settings. Autoloaded settings are read once per request.
 */
class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'value', 'autoload'];

    private ?array $cache = null;

    public function get(string $name, mixed $default = null): mixed
    {
        if ($this->cache === null) {
            $this->cache = array_column(
                $this->builder()->select('name, value')->where('autoload', 1)->get()->getResultArray(),
                'value',
                'name',
            );
        }

        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        $row = $this->where('name', $name)->first();

        return $row['value'] ?? $default;
    }

    public function put(string $name, mixed $value, bool $autoload = true): void
    {
        $value = is_scalar($value) || $value === null ? $value : json_encode($value);

        $this->db->table($this->table)->upsert(['name' => $name, 'value' => $value, 'autoload' => (int) $autoload]);

        if ($this->cache !== null) {
            $this->cache[$name] = $value;
        }
    }
}
