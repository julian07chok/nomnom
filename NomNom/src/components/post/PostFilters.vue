<!-- src/components/post/PostFilters.vue -->
<script setup>
import '../../assets/css/post-filters.css'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue'])

const cuisines = [
  'All', 'Malaysian', 'Chinese', 'Indian', 'Japanese',
  'Korean', 'Thai', 'Vietnamese', 'Western', 'Cafe', 'Dessert',
]
const locations = [
  'All', 'Kuala Lumpur', 'Petaling Jaya', 'Subang Jaya',
  'Penang', 'Johor Bahru', 'Melaka', 'Ipoh',
]
const priceRanges = ['All', '$', '$$', '$$$']

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<template>
  <aside class="post-filters" aria-label="Search filters">
    <div class="filter-group">
      <label class="filter-label" for="filter-cuisine">Cuisine</label>
      <select
        id="filter-cuisine"
        class="filter-select"
        :value="modelValue.cuisine"
        @change="update('cuisine', $event.target.value)"
      >
        <option v-for="c in cuisines" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <div class="filter-group">
      <label class="filter-label" for="filter-location">Location</label>
      <select
        id="filter-location"
        class="filter-select"
        :value="modelValue.location"
        @change="update('location', $event.target.value)"
      >
        <option v-for="l in locations" :key="l" :value="l">{{ l }}</option>
      </select>
    </div>

    <div class="filter-group">
      <label class="filter-label" for="filter-price">Price range</label>
      <select
        id="filter-price"
        class="filter-select"
        :value="modelValue.priceRange"
        @change="update('priceRange', $event.target.value)"
      >
        <option v-for="p in priceRanges" :key="p" :value="p">{{ p }}</option>
      </select>
    </div>

    <button
      v-if="
        modelValue.cuisine !== 'All' ||
        modelValue.location !== 'All' ||
        modelValue.priceRange !== 'All'
      "
      type="button"
      class="filter-clear"
      @click="
        emit('update:modelValue', {
          cuisine: 'All',
          location: 'All',
          priceRange: 'All',
        })
      "
    >
      <i class="bi bi-x-circle" aria-hidden="true"></i>
      Clear filters
    </button>
  </aside>
</template>