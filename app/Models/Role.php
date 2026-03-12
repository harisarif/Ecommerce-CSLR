<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles_permissions';

    protected $casts = [
        'permissions' => 'array',
        'is_admin' => 'boolean',
        'is_super_admin' => 'boolean',
    ];


    public function getRoleNameAttribute($value)
    {
        $data = @unserialize($value);
        return $data ?: [];
    }

    public function getName($langId = 1)
    {
        foreach ($this->role_name as $item) {
            if ($item['lang_id'] == $langId) {
                return $item['name'];
            }
        }
        // fallback
        return $this->role_name[0]['name'] ?? 'Admin';
    }
}
