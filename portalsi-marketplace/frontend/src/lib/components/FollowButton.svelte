<script lang="ts">
  import Icon from './Icon.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';

  let { vendorId, initialFollowing = false, initialCount = 0, onChange } = $props<{
    vendorId: number;
    initialFollowing?: boolean;
    initialCount?: number;
    onChange?: (data: { following: boolean; count: number }) => void;
  }>();

  let following = $state(initialFollowing);
  let count = $state(initialCount);
  let loading = $state(false);

  async function toggle() {
    if (!auth.user) { goto('/login?next=/vendors/' + vendorId); return; }
    loading = true;
    try {
      const r: any = await apiEndpoints.toggleFollow(vendorId);
      following = !!r.following;
      count = r.followers ?? count;
      onChange?.({ following, count });
      toast.success(following ? 'Mengikuti toko' : 'Berhenti mengikuti');
    } catch (e: any) {
      toast.error(e.message);
    } finally { loading = false; }
  }
</script>

<button on:click={toggle} disabled={loading}
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition
               {following ? 'bg-ink-100 text-ink-700 hover:bg-ink-200' : 'bg-app-primary text-app-pfg hover:bg-ink-800'}">
  <Icon name={following ? 'user-check' : 'user-plus'} size={14} />
  {following ? 'Mengikuti' : 'Ikuti'}
</button>
