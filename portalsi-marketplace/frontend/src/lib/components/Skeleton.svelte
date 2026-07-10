<script lang="ts">
  let { class: cls = '', w = '', h = '', rounded = 'rounded-lg' } = $props<{
    class?: string;
    w?: string;
    h?: string;
    rounded?: string;
  }>();
</script>

<span class="block animate-pulse bg-ink-100 {rounded} {cls}" style:width={w} style:height={h}></span>
