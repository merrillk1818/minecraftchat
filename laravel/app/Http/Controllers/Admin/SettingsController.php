<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', [
            'chatMaxLength' => (int) Setting::get('chat_max_length', 1000),
            'noteMaxLength' => (int) Setting::get('note_max_length', 10000),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'chat_max_length' => 'required|integer|min:100|max:10000',
            'note_max_length' => 'required|integer|min:500|max:100000',
        ]);

        Setting::set('chat_max_length', $request->integer('chat_max_length'));
        Setting::set('note_max_length', $request->integer('note_max_length'));

        return back()->with('success', 'Settings saved.');
    }
}
