<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /** List threads untuk user yang login (sebagai pembeli ATAU sebagai pemilik vendor) */
    public function index(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;
        $threads = ChatThread::where(function ($q) use ($user, $vendor) {
                $q->where('user_id', $user->id);
                if ($vendor) $q->orWhere('vendor_id', $vendor->id);
            })
            ->whereHas('messages')
            ->with(['vendor:id,name,avatar', 'user:id,name', 'product:id,name,image', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
        return response()->json($threads);
    }

    public function show(Request $request, $id)
    {
        $thread = ChatThread::with(['vendor:id,name,avatar,user_id,username,badge', 'user:id,name,email,phone', 'product:id,name,image,price,slug', 'messages.sender:id,name'])
            ->findOrFail($id);
        $this->authorizeThread($request, $thread);
        // mark messages addressed to current user as read
        $isBuyer = $thread->user_id === $request->user()->id;
        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_type', $isBuyer ? 'SELLER' : 'BUYER')
            ->update(['is_read' => true]);
        return response()->json($thread);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;
        $buyerThreadIds = ChatThread::where('user_id', $user->id)->pluck('id');
        $sellerThreadIds = $vendor ? ChatThread::where('vendor_id', $vendor->id)->pluck('id') : collect();

        if ($buyerThreadIds->isEmpty() && $sellerThreadIds->isEmpty()) {
            return response()->json(['count' => 0]);
        }

        $count = ChatMessage::where('is_read', false)
            ->where(function ($q) use ($buyerThreadIds, $sellerThreadIds) {
                if ($buyerThreadIds->isNotEmpty()) {
                    $q->orWhere(fn($w) => $w->whereIn('thread_id', $buyerThreadIds)->where('sender_type', 'SELLER'));
                }
                if ($sellerThreadIds->isNotEmpty()) {
                    $q->orWhere(fn($w) => $w->whereIn('thread_id', $sellerThreadIds)->where('sender_type', 'BUYER'));
                }
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'product_id' => 'nullable|exists:products,id',
            'message' => 'nullable|string|max:2000',
        ]);
        $vendor = Vendor::findOrFail($data['vendor_id']);
        if ($vendor->user_id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa chat ke toko sendiri'], 422);
        }
        if (in_array($vendor->moderation_mode, ['LIMITED', 'DISABLED'])) {
            return response()->json(['message' => 'Toko ini sedang tidak menerima pesan baru.'], 422);
        }
        $thread = ChatThread::firstOrCreate(
            ['user_id' => $request->user()->id, 'vendor_id' => $vendor->id],
            ['product_id' => $data['product_id'] ?? null, 'last_message_at' => null]
        );
        if (!empty($data['product_id'])) {
            $thread->product_id = $data['product_id'];
            $thread->save();
        }
        if (!empty($data['message'])) {
            ChatMessage::create([
                'thread_id' => $thread->id,
                'sender_user_id' => $request->user()->id,
                'sender_type' => 'BUYER',
                'message' => $data['message'],
            ]);
            $thread->update(['last_message_at' => now()]);
        }
        return response()->json($thread);
    }

    public function sendMessage(Request $request, $threadId)
    {
        $data = $request->validate([
            'message'   => 'nullable|string|max:2000',
            'image_url' => 'nullable|string', // data URI
        ]);
        if (empty($data['message']) && empty($data['image_url'])) {
            return response()->json(['message' => 'Pesan atau gambar harus diisi'], 422);
        }
        $thread = ChatThread::findOrFail($threadId);
        $this->authorizeThread($request, $thread);

        $isBuyer = $thread->user_id === $request->user()->id;
        $msg = ChatMessage::create([
            'thread_id'      => $thread->id,
            'sender_user_id' => $request->user()->id,
            'sender_type'    => $isBuyer ? 'BUYER' : 'SELLER',
            'message'        => $data['message'] ?? '',
            'image_url'      => $data['image_url'] ?? null,
        ]);
        $thread->update(['last_message_at' => now()]);
        return response()->json($msg->load('sender:id,name'));
    }

    private function authorizeThread(Request $request, ChatThread $thread): void
    {
        $user = $request->user();
        $isBuyer  = $thread->user_id === $user->id;
        $isSeller = $user->vendor && $user->vendor->id === $thread->vendor_id;
        if (!$isBuyer && !$isSeller) abort(403, 'Bukan thread Anda');
    }
}
