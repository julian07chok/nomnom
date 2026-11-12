<script setup>
defineProps({
  type: {
    type: String,
    default: "button",
    validator: (value) => ["button", "submit", "reset"].includes(value),
  },
  variant: {
    type: String,
    default: "primary",
    validator: (value) => ["primary", "secondary", "danger"].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  loadingText: {
    type: String,
    default: "Please wait…",
  },
  block: {
    type: Boolean,
    default: false,
  },
  mobileBlock: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <button
    class="app-button"
    :class="[
      `app-button--${variant}`,
      {
        'app-button--block': block,
        'app-button--mobile-block': mobileBlock,
      },
    ]"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading"
  >
    <template v-if="loading">{{ loadingText }}</template>
    <slot v-else />
  </button>
</template>

<style scoped src="../../assets/css/app-button.css"></style>
