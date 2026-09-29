<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedbackManagementController extends Controller
{
    /**
     * Menampilkan daftar ulasan / feedback pelanggan
     */
    public function index()
    {
        $feedbacks = Feedback::latest()->paginate(15);

        return Inertia::render('Admin/Feedbacks/Index', [
            'feedbacks' => $feedbacks
        ]);
    }

    /**
     * Memperbarui status persetujuan feedback (pending, approved, rejected)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $feedback = Feedback::findOrFail($id);
        $feedback->update(['status' => $request->status]);

        return back()->with('success', 'Status ulasan berhasil diperbarui.');
    }

    /**
     * Menambahkan atau memperbarui balasan admin pada feedback
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'admin_reply' => 'required|string|max:1000'
        ]);

        $feedback = Feedback::findOrFail($id);
        $feedback->update(['admin_reply' => $request->admin_reply]);

        return back()->with('success', 'Balasan resmi admin berhasil disimpan.');
    }

    /**
     * Menghapus ulasan
     */
    public function destroy($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->delete();

        return back()->with('success', 'Ulasan berhasil dihapus.');
    }
}
