<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Redirect;
use DB;

class RoleController extends Controller
{


    public function index()
    {
        $roles = Role::all();
        $i = 0;

        return view('pages.roles.index', compact('roles', 'i'));
    }

    public function create()
    {
        $permissions = Permission::all();

        $permisionver = Permission::where('categoria','Ver')->get();
        $permisionCrear = Permission::where('categoria','Crear')->get();
        $permisionEditar = Permission::where('categoria','Editar')->get();
        $permisionEliminar = Permission::where('categoria','Eliminar')->get();
        $permisionReportes = Permission::where('categoria','Reportes')->get();
        return view('pages.roles.create', compact('permisionver','permisionCrear','permisionEditar','permisionEliminar','permisionReportes','permissions'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|regex:/^[a-zA-Z\s]+$/u|max:75',
        ]);
        $role = new Role();
        $role->name = strtoupper($request->name);
        $role->save();
        $role->permissions()->sync($request->permissions);

        return redirect()->route('roles.index')
            ->with('success', 'Rol Agregado Correctamente.');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::groupBy('categoria','id')->orderBy('categoria','asc')->get();



        return view('pages.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|regex:/^[a-zA-Z\s]+$/u|max:75',
        ]);
        $role->name = strtoupper($request->name);
        $role->save();
        $role->permissions()->sync($request->permissions);

        return redirect()->route('roles.index')
            ->with('success', 'Rol Actualizado Correctamente.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('roles.index')
            ->with('success', 'Rol Eliminado Correctamente Correctamente.');
    }
}
