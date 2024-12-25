<?php


namespace App\Http\Controllers\SuperAdmin;


use Spatie\Permission\Models\Permission;

class PermissionController
{
    public function index() {
        return Permission::all();
    }
}