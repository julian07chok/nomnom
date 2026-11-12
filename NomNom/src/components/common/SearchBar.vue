<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  placeholder: {
    type: String,
    default: "Search dishes, restaurants, locations…",
  },
  variant: {
    type: String,
    default: "home",
    validator: (value) => ["home", "explore"].includes(value),
  },
  showSubmit: {
    type: Boolean,
    default: true,
  },
  clearable: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    default: "Search food posts",
  },
});

const emit = defineEmits(["update:modelValue", "submit"]);
const inputElement = ref(null);

const query = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

function clearSearch() {
  query.value = "";
  inputElement.value?.focus();
}

function submitSearch() {
  emit("submit", query.value.trim());
}
</script>

<template>
  <form
    class="nomnom-search"
    :class="`nomnom-search--${variant}`"
    role="search"
    :aria-label="ariaLabel"
    @submit.prevent="submitSearch"
  >
    <i class="bi bi-search nomnom-search__icon" aria-hidden="true"></i>

    <input
      ref="inputElement"
      v-model="query"
      class="nomnom-search__input"
      type="search"
      :placeholder="placeholder"
      :aria-label="ariaLabel"
    />

    <button
      v-if="clearable && query"
      class="nomnom-search__clear"
      type="button"
      aria-label="Clear search"
      @click="clearSearch"
    >
      <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <button v-if="showSubmit" class="nomnom-search__submit" type="submit">
      Search
    </button>
  </form>
</template>

<style scoped src="../../assets/css/search-bar.css"></style>
