<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    public function index()
    {
        $sellers = User::where('role', 'seller')->get();
        return view('admin.sellers.index', compact('sellers'));
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Seller deleted.');
    }
}
