<!-- src/components/Pagination.vue -->
<script setup>
import Paginate from 'vuejs-paginate-next'

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  perPage: { type: Number, default: 6 },
  totalItems: { type: Number, default: 0 },
  scrollTargetId: { type: String, default: '' },
})

const emit = defineEmits(['update:currentPage'])

// clickCallback — receives the clicked page number as parameter
// (Lecture 09, page 9: "Use clicked page number as parameter")
function clickCallback(pageNum) {
  const page = Number(pageNum)
  if (page < 1 || page > props.totalPages || page === props.currentPage) return

  emit('update:currentPage', page)

  const target = document.getElementById(props.scrollTargetId)
  target?.scrollIntoView({ behavior: 'smooth', block: 'start' })
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

<style scoped>
.pagination-nav {
  display: flex;
  justify-content: center;
  margin-top: 40px;
}

/* The library renders a <ul class="pagination">. We style it
   to match the NomNom palette. Use :deep() because these elements
   are generated inside the child component. */
.pagination-nav :deep(.pagination) {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pagination-nav :deep(.page-item) {
  display: inline-flex;
}

.pagination-nav :deep(.page-link) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  height: 42px;
  padding: 0 14px;
  border: 1px solid rgb(72 57 52 / 20%);
  border-radius: 12px;
  background: #fffcf3;
  color: #483934;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease,
    border-color 150ms ease;
}

.pagination-nav :deep(.page-link:hover) {
  background: #f4f2c2;
}

.pagination-nav :deep(.currentPage .page-link) {
  background: #483934;
  color: #fffcf3;
  border-color: #483934;
}

.pagination-nav :deep(.disabled .page-link) {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}

.pagination-nav :deep(.page-link:focus-visible) {
  outline: 3px solid #cd4639;
  outline-offset: 3px;
}

@media (max-width: 480px) {
  .pagination-nav :deep(.page-link) {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    font-size: 0.9rem;
  }
}
</style>