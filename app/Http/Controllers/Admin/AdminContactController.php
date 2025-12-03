<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/contacts/index', compact('contacts'));
    }

    public function show($id)
    {
        $contact = Contact::with('user')->findOrFail($id);

        return Inertia::render('admin/contacts/show', compact('contact'));
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'admin_reply' => 'required|string',
        ]);

        $contact = Contact::findOrFail($id);
        $contact->update([
            'admin_reply' => $request->admin_reply,
            'status' => 'replied',
        ]);

        return redirect()->back()->with('success', 'Balasan berhasil dikirim!');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,replied,closed',
        ]);

        $contact = Contact::findOrFail($id);
        $contact->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('success', 'Pesan berhasil dihapus!');
    }
}
