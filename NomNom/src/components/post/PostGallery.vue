<!-- src/components/post/PostGallery.vue -->
<script setup>
import { computed, ref } from 'vue'
import '../../assets/css/post-gallery.css'

const props = defineProps({
  images: { type: Array, required: true },
  alt: { type: String, default: '' },
})

// Which image is currently shown as the main one?
const activeIndex = ref(0)

// Computed: safe access to the currently displayed image
const activeImage = computed(() => props.images?.[activeIndex.value] || '')

// Computed: show thumbnails only if there are 2 or more images
const hasThumbnails = computed(() => props.images?.length > 1)

function selectImage(index) {
  activeIndex.value = index
}
</script>

<template>
  <div class="post-gallery">
    <!-- Main image -->
    <figure class="gallery-main">
      <img
        v-if="activeImage"
        :src="activeImage"
        :alt="alt"
        class="gallery-main-img"
      />
      <div v-else class="gallery-empty">No image</div>
    </figure>

    <!-- Thumbnails -->
    <ul v-if="hasThumbnails" class="gallery-thumbs">
      <li v-for="(img, index) in images" :key="index">
        <button
          type="button"
          class="thumb-btn"
          :class="{ 'is-active': index === activeIndex }"
          :aria-label="`View image ${index + 1}`"
          :aria-current="index === activeIndex ? 'true' : undefined"
          @click="selectImage(index)"
        >
          <img :src="img" :alt="`${alt} ${index + 1}`" />
        </button>
      </li>
    </ul>
  </div>
</template>