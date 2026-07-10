<script lang="ts">
  import IconifyIcon from '@iconify/svelte';
  let { name, size = 18, class: cls = '', fill = 'none' } = $props<{ name: string; size?: number | string; class?: string; fill?: string }>();
</script>

<IconifyIcon icon={name.includes(':') ? name : `lucide:${name}`} width={size} height={size} class={cls} {fill} />
