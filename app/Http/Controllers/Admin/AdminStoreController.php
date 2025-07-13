<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function index()
    {
        $stores = Store::all();
        return view('admin.stores.index', compact('stores'));
    }

    public function destroy($id)
    {
        Store::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Store deleted.');
    }
}
