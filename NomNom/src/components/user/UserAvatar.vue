<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  src: {
    type: String,
    default: "",
  },
  size: {
    type: Number,
    default: 38,
  },
});

const imageFailed = ref(false);

watch(
  () => props.src,
  () => {
    imageFailed.value = false;
  },
);
</script>

<template>
  <span
    class="user-avatar"
    :style="{ width: `${size}px`, height: `${size}px` }"
    aria-hidden="true"
  >
    <img
      v-if="src && !imageFailed"
      :src="src"
      alt=""
      @error="imageFailed = true"
    />
    <i v-else class="bi bi-person-fill"></i>
  </span>
</template>

<style scoped>
.user-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 50%;
  background: #dce9fc;
  color: #483934;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar i {
  font-size: 1.4em;
}
</style>
