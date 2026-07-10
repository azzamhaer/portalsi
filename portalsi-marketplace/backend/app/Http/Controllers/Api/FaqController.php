<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    /** Public: ambil semua FAQ aktif, grouped by section */
    public function index()
    {
        $faqs = Faq::where('is_active', true)->orderBy('section')->orderBy('sort_order')->get();
        $grouped = $faqs->groupBy('section')->map(function ($items, $section) {
            return [
                'title' => $section,
                'items' => $items->map(fn($f) => ['q' => $f->question, 'a' => $f->answer])->values(),
            ];
        })->values();
        return response()->json($grouped);
    }

    /** Admin: ambil semua FAQ (termasuk inactive) */
    public function adminList()
    {
        return response()->json(Faq::orderBy('section')->orderBy('sort_order')->get());
    }

    public function adminSave(Request $request)
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.section'    => 'required|string|max:100',
            'items.*.question'   => 'required|string|max:300',
            'items.*.answer'     => 'required|string|max:5000',
            'items.*.is_active'  => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($data) {
            Faq::query()->delete();
            foreach ($data['items'] as $i => $it) {
                Faq::create([
                    'section'    => $it['section'],
                    'question'   => $it['question'],
                    'answer'     => $it['answer'],
                    'sort_order' => $i,
                    'is_active'  => $it['is_active'] ?? true,
                ]);
            }
        });
        return response()->json(['ok' => true]);
    }
}
