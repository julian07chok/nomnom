<script setup>
import { nextTick } from 'vue'                       // ✅ 1. Import nextTick
import Paginate from 'vuejs-paginate-next'
import '../../assets/css/pagination.css'

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  perPage: { type: Number, default: 6 },
  totalItems: { type: Number, default: 0 },
  scrollTargetId: { type: String, default: '' },
})

const emit = defineEmits(['update:currentPage'])

async function clickCallback(pageNum) {              // ✅ 2. Mark async
  const page = Number(pageNum)
  if (page < 1 || page > props.totalPages || page === props.currentPage) return

  // 1. Tell parent to change page
  emit('update:currentPage', page)

  // 2. Wait for Vue to re-render the new list
  await nextTick()                                   // ✅ 3. Wait

  // 3. Now scroll
  if (!props.scrollTargetId) return
  const target = document.getElementById(props.scrollTargetId)
  if (target) {
    const y = target.getBoundingClientRect().top + window.scrollY - 20
    window.scrollTo({ top: y, behavior: 'smooth' })
  }
}
</script>

<template>
  <nav class="pagination-nav" aria-label="Pagination">
    <Paginate
      :page-count="totalPages"
      :page-range="3"
      :margin-pages="1"
      :click-handler="clickCallback"
      :prev-text="'‹ Prev'"
      :next-text="'Next ›'"
      :container-class="'pagination'"
      :page-class="'page-item'"
      :page-link-class="'page-link'"
      :prev-class="'page-item'"
      :prev-link-class="'page-link'"
      :next-class="'page-item'"
      :next-link-class="'page-link'"
      :active-class="'currentPage'"
      :disabled-class="'disabled'"
      :break-view-class="'page-item disabled'"
      :break-view-link-class="'page-link'"
      :initial-page="currentPage"
    />
  </nav>
</template>