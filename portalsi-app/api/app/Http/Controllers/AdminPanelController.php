<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Appeal;
use App\Models\Comment;
use App\Models\DirectMessage;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AdminPanelController extends Controller
{
    public function me(Request $request)
    {
        return response()->json([
            'admin' => $this->userPayload($request->user(), true),
        ]);
    }

    public function overview(Request $request)
    {
        $this->audit($request, 'admin.overview.viewed', 'dashboard');

        return response()->json([
            'users' => [
                'total' => User::count(),
                'verified' => User::where('is_verified', true)->count(),
                'banned' => User::where('is_banned', true)->count(),
                'online' => User::where('is_online', true)->count(),
                'active_24h' => User::where('last_activity', '>=', now()->subDay())->count(),
            ],
            'content' => [
                'posts' => Post::count(),
                'comments' => Comment::count(),
                'stories' => Story::count(),
                'groups' => Group::count(),
                'direct_messages' => DirectMessage::count(),
                'group_messages' => GroupMessage::count(),
            ],
            'moderation' => [
                'appeals_pending' => Appeal::where('status', 'pending')->count(),
            ],
            'recent_users' => User::latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (User $user) => $this->userPayload($user, true)),
            'recent_audit_logs' => AdminAuditLog::with('actor')
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = AdminAuditLog::with('actor')->latest();

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->query('action').'%');
        }
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->query('target_type'));
        }
        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', $request->query('actor_user_id'));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function appeals(Request $request)
    {
        $query = Appeal::with(['user', 'reviewer'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function resolveAppeal(Request $request, Appeal $appeal)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_response' => ['nullable', 'string', 'max:3000'],
        ]);

        $appeal->status = $validated['decision'];
        $appeal->admin_response = $validated['admin_response'] ?? null;
        $appeal->reviewed_by = $request->user()->user_id;
        $appeal->reviewed_at = now();
        $appeal->save();

        // Banding disetujui → buka blokir user.
        if ($validated['decision'] === 'approved' && $appeal->user) {
            $this->applyBanState($appeal->user, false, $request->user()->user_id);
            $appeal->user->save();
        }

        $this->audit($request, 'appeal.'.$validated['decision'], 'appeal', $appeal->appeal_id, [
            'user_id' => $appeal->user_id,
        ]);

        return response()->json([
            'message' => $validated['decision'] === 'approved' ? 'Banding disetujui, blokir dibuka.' : 'Banding ditolak.',
            'appeal' => $appeal->fresh(['user', 'reviewer']),
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }
        if ($request->filled('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }
        if ($request->filled('is_banned')) {
            $query->where('is_banned', $request->boolean('is_banned'));
        }

        $sort = in_array($request->query('sort'), ['created_at', 'last_activity', 'username', 'role'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return response()->json(
            $query->orderBy($sort, $direction)
                ->paginate($this->perPage($request))
                ->through(fn (User $user) => $this->userPayload($user, true))
        );
    }

    public function user(Request $request, User $user)
    {
        $this->audit($request, 'user.viewed', 'user', $user->user_id);

        return response()->json([
            'user' => $this->userPayload($user, true),
            'stats' => [
                'posts' => $user->posts()->count(),
                'comments' => $user->comments()->count(),
                'stories' => $user->stories()->count(),
                'sent_direct_messages' => $user->sentMessages()->count(),
                'received_direct_messages' => $user->receivedMessages()->count(),
                'group_messages' => $user->groupMessages()->count(),
                'groups_owned' => $user->ownedGroups()->count(),
                'groups_joined' => $user->groupMemberships()->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
            ],
            'recent_login_histories' => $user->loginHistories()->latest('login_at')->limit(20)->get(),
            'recent_direct_messages' => DirectMessage::with(['sender', 'receiver'])
                ->where('sender_id', $user->user_id)
                ->orWhere('receiver_id', $user->user_id)
                ->latest('sent_at')
                ->limit(20)
                ->get()
                ->map(fn (DirectMessage $message) => $this->directMessagePayload($message)),
            'recent_group_messages' => GroupMessage::with(['sender', 'group'])
                ->where('sender_id', $user->user_id)
                ->latest('sent_at')
                ->limit(20)
                ->get()
                ->map(fn (GroupMessage $message) => $this->groupMessagePayload($message)),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $actor = $request->user();
        $validated = $request->validate([
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._]+$/',
                Rule::unique('users', 'username')->ignore($user->user_id, 'user_id'),
            ],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'role' => ['sometimes', Rule::in(['dev', 'teacher', 'parent', 'student', 'other'])],
            'is_verified' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
            'is_banned' => ['sometimes', 'boolean'],
            'ban_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
        ]);

        if ($actor->user_id === $user->user_id) {
            if (array_key_exists('is_banned', $validated) && (bool) $validated['is_banned']) {
                return response()->json(['message' => 'Admin tidak bisa memblokir akun sendiri.'], 422);
            }
            if (array_key_exists('is_verified', $validated) && ! (bool) $validated['is_verified']) {
                return response()->json(['message' => 'Admin tidak bisa mencabut akses admin akun sendiri.'], 422);
            }
        }

        $before = $this->userPayload($user, true);

        foreach (['full_name', 'bio', 'role', 'ban_reason', 'admin_notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('username', $validated)) {
            $user->username = strtolower($validated['username']);
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'] ? strtolower($validated['email']) : null;
        }
        foreach (['is_verified', 'is_private'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = (bool) $validated[$field];
            }
        }
        if (array_key_exists('password', $validated) && $validated['password']) {
            $user->password_hash = Hash::make($validated['password']);
            $user->tokens()->delete();
        }
        if (array_key_exists('is_banned', $validated)) {
            $this->applyBanState($user, (bool) $validated['is_banned'], $actor->user_id, $validated['ban_reason'] ?? $user->ban_reason);
        }

        $user->save();

        $this->audit($request, 'user.updated', 'user', $user->user_id, [
            'before' => $before,
            'after' => $this->userPayload($user->fresh(), true),
        ]);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user' => $this->userPayload($user->fresh(), true),
        ]);
    }

    public function banUser(Request $request, User $user)
    {
        if ($request->user()->user_id === $user->user_id) {
            return response()->json(['message' => 'Admin tidak bisa memblokir akun sendiri.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->applyBanState($user, true, $request->user()->user_id, $validated['reason'] ?? null);
        if (array_key_exists('admin_notes', $validated)) {
            $user->admin_notes = $validated['admin_notes'];
        }
        $user->save();
        $user->tokens()->delete();

        $this->audit($request, 'user.banned', 'user', $user->user_id, [
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'User berhasil diblokir dan semua token aktif dicabut.',
            'user' => $this->userPayload($user->fresh(), true),
        ]);
    }

    public function unbanUser(Request $request, User $user)
    {
        $this->applyBanState($user, false, $request->user()->user_id);
        $user->save();

        $this->audit($request, 'user.unbanned', 'user', $user->user_id);

        return response()->json([
            'message' => 'Blokir user berhasil dibuka.',
            'user' => $this->userPayload($user->fresh(), true),
        ]);
    }

    public function deleteUser(Request $request, User $user)
    {
        if ($request->user()->user_id === $user->user_id) {
            return response()->json(['message' => 'Admin tidak bisa menghapus akun sendiri.'], 422);
        }

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['anonymize', 'force'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $mode = $validated['mode'] ?? 'anonymize';
        $payload = $this->userPayload($user, true);

        DB::transaction(function () use ($request, $user, $mode, $validated, $payload) {
            $user->tokens()->delete();

            if ($mode === 'force') {
                $userId = $user->user_id;
                $this->audit($request, 'user.force_deleted', 'user', $userId, [
                    'reason' => $validated['reason'] ?? null,
                    'snapshot' => $payload,
                ]);
                $user->delete();

                return;
            }

            $user->forceFill([
                'username' => 'deleted_user_'.$user->user_id,
                'full_name' => 'Akun dihapus admin',
                'email' => null,
                'password_hash' => Hash::make(Str::random(40)),
                'bio' => null,
                'profile_picture_url' => null,
                'banner_url' => null,
                'is_private' => true,
                'is_banned' => true,
                'banned_at' => now(),
                'banned_by' => $request->user()->user_id,
                'ban_reason' => $validated['reason'] ?? 'Akun dianonimkan oleh admin.',
            ])->save();

            $this->audit($request, 'user.anonymized', 'user', $user->user_id, [
                'reason' => $validated['reason'] ?? null,
                'snapshot' => $payload,
            ]);
        });

        return response()->json([
            'message' => $mode === 'force' ? 'User berhasil dihapus permanen.' : 'User berhasil dianonimkan dan diblokir.',
        ]);
    }

    public function directMessages(Request $request)
    {
        $query = DirectMessage::with(['sender', 'receiver'])->latest('sent_at');

        if ($request->filled('user_id')) {
            $userId = (int) $request->query('user_id');
            $query->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId));
        }
        if ($request->filled('sender_id')) {
            $query->where('sender_id', $request->query('sender_id'));
        }
        if ($request->filled('receiver_id')) {
            $query->where('receiver_id', $request->query('receiver_id'));
        }
        if ($request->filled('search')) {
            $query->where('content', 'like', '%'.$request->query('search').'%');
        }

        return response()->json(
            $query->paginate($this->perPage($request))
                ->through(fn (DirectMessage $message) => $this->directMessagePayload($message))
        );
    }

    public function updateDirectMessage(Request $request, DirectMessage $message)
    {
        $validated = $request->validate([
            'content' => ['sometimes', 'nullable', 'string'],
            'media_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_read' => ['sometimes', 'boolean'],
        ]);

        foreach (['content', 'media_url'] as $field) {
            if (array_key_exists($field, $validated)) {
                $message->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('is_read', $validated)) {
            $message->is_read = (bool) $validated['is_read'];
        }
        $message->save();

        $this->audit($request, 'direct_message.updated', 'direct_message', $message->message_id, $validated);

        return response()->json([
            'message' => 'Direct message berhasil diperbarui.',
            'direct_message' => $this->directMessagePayload($message->fresh(['sender', 'receiver'])),
        ]);
    }

    public function deleteDirectMessage(Request $request, DirectMessage $message)
    {
        $messageId = $message->message_id;
        $snapshot = $this->directMessagePayload($message->loadMissing(['sender', 'receiver']));
        $message->delete();

        $this->audit($request, 'direct_message.deleted', 'direct_message', $messageId, [
            'snapshot' => $snapshot,
        ]);

        return response()->json(['message' => 'Direct message berhasil dihapus.']);
    }

    public function groupMessages(Request $request)
    {
        $query = GroupMessage::with(['sender', 'group'])->latest('sent_at');

        foreach (['group_id', 'sender_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
        if ($request->filled('is_deleted')) {
            $query->where('is_deleted', $request->boolean('is_deleted'));
        }
        if ($request->filled('is_pinned')) {
            $query->where('is_pinned', $request->boolean('is_pinned'));
        }
        if ($request->filled('search')) {
            $query->where('content', 'like', '%'.$request->query('search').'%');
        }

        return response()->json(
            $query->paginate($this->perPage($request))
                ->through(fn (GroupMessage $message) => $this->groupMessagePayload($message))
        );
    }

    public function updateGroupMessage(Request $request, GroupMessage $message)
    {
        $validated = $request->validate([
            'content' => ['sometimes', 'nullable', 'string'],
            'media_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_deleted' => ['sometimes', 'boolean'],
        ]);

        foreach (['content', 'media_url'] as $field) {
            if (array_key_exists($field, $validated)) {
                $message->{$field} = $validated[$field];
            }
        }
        foreach (['is_pinned', 'is_deleted'] as $field) {
            if (array_key_exists($field, $validated)) {
                $message->{$field} = (bool) $validated[$field];
            }
        }
        if ($message->is_deleted) {
            $message->content = null;
            $message->media_url = null;
            $message->is_pinned = false;
        }
        $message->is_edited = true;
        $message->save();

        $this->audit($request, 'group_message.updated', 'group_message', $message->id, $validated);

        return response()->json([
            'message' => 'Group message berhasil diperbarui.',
            'group_message' => $this->groupMessagePayload($message->fresh(['sender', 'group'])),
        ]);
    }

    public function deleteGroupMessage(Request $request, GroupMessage $message)
    {
        $message->forceFill([
            'content' => null,
            'media_url' => null,
            'is_deleted' => true,
            'is_pinned' => false,
            'is_edited' => true,
        ])->save();

        $this->audit($request, 'group_message.deleted', 'group_message', $message->id);

        return response()->json(['message' => 'Group message berhasil disembunyikan.']);
    }

    public function posts(Request $request)
    {
        $query = Post::with('user')->withCount(['comments', 'likes'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }
        if ($request->filled('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }
        if ($request->filled('search')) {
            $query->where('caption', 'like', '%'.$request->query('search').'%');
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updatePost(Request $request, Post $post)
    {
        $validated = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        foreach (['caption', 'location'] as $field) {
            if (array_key_exists($field, $validated)) {
                $post->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('is_archived', $validated)) {
            $post->is_archived = (bool) $validated['is_archived'];
        }
        $post->save();

        $this->audit($request, 'post.updated', 'post', $post->post_id, $validated);

        return response()->json(['message' => 'Post berhasil diperbarui.', 'post' => $post->fresh('user')]);
    }

    public function deletePost(Request $request, Post $post)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $reason = $validated['reason'] ?? null;
        $postId = $post->post_id;
        $ownerId = $post->user_id;
        $post->delete();
        $this->audit($request, 'post.deleted', 'post', $postId, ['reason' => $reason]);
        $this->notifyModeration($request, $ownerId, 'Postingan kamu', $reason);

        return response()->json(['message' => 'Post berhasil dihapus.']);
    }

    public function comments(Request $request)
    {
        $query = Comment::with(['user', 'post'])->latest();

        foreach (['user_id', 'post_id', 'parent_comment_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
        if ($request->filled('search')) {
            $query->where('content', 'like', '%'.$request->query('search').'%');
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updateComment(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'content' => ['sometimes', 'nullable', 'string'],
            'gif_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);
        $comment->fill($validated)->save();

        $this->audit($request, 'comment.updated', 'comment', $comment->comment_id, $validated);

        return response()->json(['message' => 'Komentar berhasil diperbarui.', 'comment' => $comment->fresh(['user', 'post'])]);
    }

    public function deleteComment(Request $request, Comment $comment)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $reason = $validated['reason'] ?? null;
        $commentId = $comment->comment_id;
        $ownerId = $comment->user_id;
        $comment->delete();
        $this->audit($request, 'comment.deleted', 'comment', $commentId, ['reason' => $reason]);
        $this->notifyModeration($request, $ownerId, 'Komentar kamu', $reason);

        return response()->json(['message' => 'Komentar berhasil dihapus.']);
    }

    public function stories(Request $request)
    {
        $query = Story::with('user')->latest('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('expired')) {
            $request->boolean('expired')
                ? $query->where('expires_at', '<=', now())
                : $query->where('expires_at', '>', now());
        }
        if ($request->filled('search')) {
            $query->where('caption', 'like', '%'.$request->query('search').'%');
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updateStory(Request $request, Story $story)
    {
        $validated = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);
        $story->fill($validated)->save();

        $this->audit($request, 'story.updated', 'story', $story->story_id, $validated);

        return response()->json(['message' => 'Story berhasil diperbarui.', 'story' => $story->fresh('user')]);
    }

    public function deleteStory(Request $request, Story $story)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $reason = $validated['reason'] ?? null;
        $storyId = $story->story_id;
        $ownerId = $story->user_id;
        $story->delete();
        $this->audit($request, 'story.deleted', 'story', $storyId, ['reason' => $reason]);
        $this->notifyModeration($request, $ownerId, 'Cerita kamu', $reason);

        return response()->json(['message' => 'Story berhasil dihapus.']);
    }

    /**
     * Kirim notifikasi in-app ke pemilik konten yang dimoderasi.
     * Tidak menggagalkan aksi moderasi kalau pembuatan notifikasi gagal.
     */
    private function notifyModeration(Request $request, ?int $ownerId, string $subject, ?string $reason): void
    {
        $adminId = $request->user()->user_id;
        if (! $ownerId || $ownerId === $adminId) {
            return;
        }
        $message = $reason
            ? "{$subject} dihapus oleh admin. Alasan: {$reason}"
            : "{$subject} dihapus oleh admin karena melanggar aturan komunitas.";
        try {
            Notification::create([
                'recipient_id' => $ownerId,
                'type' => 'moderation',
                'message' => $message,
                'related_user_id' => $adminId,
                'related_post_id' => null,
                'created_at' => now(),
                'is_read' => false,
            ]);
        } catch (Throwable $e) {
            // abaikan — notifikasi bersifat pelengkap
        }
    }

    public function groups(Request $request)
    {
        $query = Group::with('owner')->withCount(['members', 'messages'])->latest();

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->query('owner_id'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updateGroup(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,user_id'],
        ]);
        $group->fill($validated)->save();

        $this->audit($request, 'group.updated', 'group', $group->id, $validated);

        return response()->json(['message' => 'Group berhasil diperbarui.', 'group' => $group->fresh('owner')]);
    }

    public function deleteGroup(Request $request, Group $group)
    {
        $groupId = $group->id;
        $group->delete();
        $this->audit($request, 'group.deleted', 'group', $groupId);

        return response()->json(['message' => 'Group berhasil dihapus.']);
    }

    private function perPage(Request $request, int $default = 25): int
    {
        $value = (int) $request->query('per_page', $default);

        return max(1, min(100, $value));
    }

    private function userPayload(User $user, bool $includeModeration = false): array
    {
        $payload = [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'bio' => $user->bio,
            'profile_picture_url' => $user->profile_picture_url,
            'banner_url' => $user->banner_url,
            'is_verified' => (bool) $user->is_verified,
            'is_private' => (bool) $user->is_private,
            'is_online' => (bool) $user->is_online,
            'email_verified_at' => $user->email_verified_at,
            'last_seen' => $user->last_seen,
            'last_activity' => $user->last_activity,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($includeModeration) {
            $payload += [
                'is_banned' => (bool) ($user->is_banned ?? false),
                'banned_at' => $user->banned_at,
                'banned_by' => $user->banned_by,
                'ban_reason' => $user->ban_reason,
                'admin_notes' => $user->admin_notes,
            ];
        }

        return $payload;
    }

    private function directMessagePayload(DirectMessage $message): array
    {
        return [
            'message_id' => $message->message_id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'content' => $message->content,
            'media_url' => $message->media_url,
            'is_read' => (bool) $message->is_read,
            'sent_at' => $message->sent_at,
            'is_story_response' => (bool) ($message->is_story_response ?? false),
            'story_id' => $message->story_id ?? null,
            'sender' => $message->relationLoaded('sender') && $message->sender ? $this->userPayload($message->sender) : null,
            'receiver' => $message->relationLoaded('receiver') && $message->receiver ? $this->userPayload($message->receiver) : null,
        ];
    }

    private function groupMessagePayload(GroupMessage $message): array
    {
        return [
            'id' => $message->id,
            'group_id' => $message->group_id,
            'sender_id' => $message->sender_id,
            'content' => $message->content,
            'media_url' => $message->media_url,
            'is_pinned' => (bool) $message->is_pinned,
            'is_edited' => (bool) $message->is_edited,
            'is_deleted' => (bool) $message->is_deleted,
            'reply_to' => $message->reply_to,
            'sent_at' => $message->sent_at,
            'sender' => $message->relationLoaded('sender') && $message->sender ? $this->userPayload($message->sender) : null,
            'group' => $message->relationLoaded('group') ? $message->group : null,
        ];
    }

    private function applyBanState(User $user, bool $isBanned, ?int $actorId = null, ?string $reason = null): void
    {
        $user->is_banned = $isBanned;

        if ($isBanned) {
            $user->banned_at = $user->banned_at ?: now();
            $user->banned_by = $actorId;
            if ($reason !== null) {
                $user->ban_reason = $reason;
            }
            $user->tokens()->delete();

            return;
        }

        $user->banned_at = null;
        $user->banned_by = null;
        $user->ban_reason = null;
    }

    private function audit(Request $request, string $action, ?string $targetType = null, mixed $targetId = null, array $metadata = []): void
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        try {
            AdminAuditLog::create([
                'actor_user_id' => $request->user()?->user_id,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId !== null ? (string) $targetId : null,
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
