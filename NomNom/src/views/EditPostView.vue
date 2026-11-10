<script setup>
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import { usePosts } from "../services/posts";

const route = useRoute();
const router = useRouter();
const { currentUser } = useAuth();
const { posts, updatePost, deletePost } = usePosts();

// ── Refs ────────────────────────────────────────────────────
const formElement = ref(null);
const fileInput = ref(null);
const photos = ref([]);
const photoError = ref("");
const publishError = ref("");
const isReading = ref(false);
const isPublishing = ref(false);
const submitted = ref(false);
const dragOver = ref(false);
const notFound = ref(false);
const notOwner = ref(false);
const loaded = ref(false);

const MAX_PHOTOS = 5;
const MAX_FILE_SIZE = 500 * 1024;
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"];

const cuisines = [
  "Malaysian",
  "Chinese",
  "Indian",
  "Japanese",
  "Korean",
  "Thai",
  "Vietnamese",
  "Italian",
  "Western",
  "Cafe",
  "Dessert",
  "Fusion",
  "Middle Eastern",
];

const locations = [
  "Kuala Lumpur",
  "Petaling Jaya",
  "Subang Jaya",
  "Cyberjaya",
  "Penang",
  "Johor Bahru",
  "Melaka",
  "Ipoh",
];

const fields = [
  {
    name: "title",
    label: "Post title",
    placeholder: "A hidden gem worth sharing",
    max: 100,
  },
  {
    name: "description",
    label: "Your food experience",
    placeholder: "What did you try? What made it memorable?",
    max: 2000,
    multiline: true,
  },
  {
    name: "restaurantName",
    label: "Restaurant name",
    placeholder: "Enter the restaurant name",
    max: 120,
  },
  {
    name: "address",
    label: "Restaurant address",
    placeholder: "Enter the street address",
    max: 250,
  },
];

const form = reactive({
  title: "",
  description: "",
  restaurantName: "",
  address: "",
  location: "",
  cuisine: "",
  priceRange: "",
});

const errors = reactive({});

// ── Unique id (browser-safe) ────────────────────────────────
function uid() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `p-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

// ── Load post from route ────────────────────────────────────
function loadPost() {
  const stringId = String(route.params.id);
  const found = posts.value.find((p) => String(p.id) === stringId);

  if (!found) {
    notFound.value = true;
    notOwner.value = false;
    loaded.value = true;
    return;
  }

  if (
    !currentUser.value ||
    String(found.authorId) !== String(currentUser.value.id)
  ) {
    notOwner.value = true;
    notFound.value = false;
    loaded.value = true;
    return;
  }

  notFound.value = false;
  notOwner.value = false;
  loaded.value = true;

  form.title = found.title || "";
  form.description = found.description || "";
  form.restaurantName = found.restaurantName || "";
  form.address = found.address || "";
  form.location = found.location || "";
  form.cuisine = found.cuisine || "";
  form.priceRange = found.priceRange || "";

  photos.value = found.galleryItems.map((image, index) => ({
    id: uid(),
    imageId: image.id,
    name: `photo-${index + 1}`,
    src: image.url,
  }));

  submitted.value = false;
  publishError.value = "";
  photoError.value = "";
  Object.keys(errors).forEach((key) => delete errors[key]);
}

watch(
  () => route.params.id,
  () => {
    loaded.value = false;
    loadPost();
  },
  { immediate: true },
);

// ── File handling ───────────────────────────────────────────
function readPhoto(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error("Could not read this photo."));
    reader.onload = () => {
      const image = new Image();
      image.onload = () =>
        resolve({
          id: uid(),
          imageId: null,
          name: file.name,
          src: reader.result,
        });
      image.onerror = () =>
        reject(new Error(`${file.name} is not a readable image.`));
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

function pickFile() {
  fileInput.value?.click();
}

function onDragOver() {
  dragOver.value = true;
}

function onDragLeave() {
  dragOver.value = false;
}

function onDrop(event) {
  dragOver.value = false;
  addFiles(event.dataTransfer?.files);
}

function onFileSelected(event) {
  const files = Array.from(event.target.files || []);
  event.target.value = "";
  addFiles(files);
}

async function addFiles(fileList) {
  if (isReading.value || isPublishing.value) return;

  const files = Array.from(fileList || []);
  if (!files.length) return;

  photoError.value = "";
  publishError.value = "";

  if (photos.value.length + files.length > MAX_PHOTOS) {
    photoError.value = `You can add up to ${MAX_PHOTOS} photos.`;
    return;
  }

  const invalidFile = files.find(
    (file) => !ALLOWED_TYPES.includes(file.type) || file.size > MAX_FILE_SIZE,
  );

  if (invalidFile) {
    photoError.value = `${invalidFile.name}: use JPG, PNG, or WebP, up to 500 KB per photo.`;
    return;
  }

  isReading.value = true;

  try {
    const newPhotos = await Promise.all(files.map(readPhoto));
    photos.value.push(...newPhotos);
    delete errors.photos;
  } catch (error) {
    photoError.value = error.message;
  } finally {
    isReading.value = false;
  }
}

function removePhoto(id) {
  if (isReading.value || isPublishing.value) return;
  photos.value = photos.value.filter((photo) => photo.id !== id);
  photoError.value = "";
  publishError.value = "";
}

function movePhoto(index, direction) {
  if (isReading.value || isPublishing.value) return;
  const target = index + direction;
  if (target < 0 || target >= photos.value.length) return;
  const list = [...photos.value];
  [list[index], list[target]] = [list[target], list[index]];
  photos.value = list;
}

function clearError(name) {
  delete errors[name];
  publishError.value = "";
}

// ── Validation ──────────────────────────────────────────────
function validate() {
  Object.keys(errors).forEach((key) => delete errors[key]);

  for (const field of fields) {
    const value = form[field.name].trim();
    if (!value) {
      errors[field.name] = `Please enter ${field.label.toLowerCase()}.`;
    } else if (value.length > field.max) {
      errors[field.name] = `Use no more than ${field.max} characters.`;
    }
  }

  if (!locations.includes(form.location)) {
    errors.location = "Please select a location.";
  }
  if (!cuisines.includes(form.cuisine)) {
    errors.cuisine = "Please select a cuisine.";
  }
  if (!["$", "$$", "$$$"].includes(form.priceRange)) {
    errors.priceRange = "Please select a price range.";
  }
  if (!photos.value.length) {
    errors.photos = "Please add at least one photo.";
  }

  return Object.keys(errors).length === 0;
}

// ── Save ────────────────────────────────────────────────────
async function save() {
  if (isReading.value || isPublishing.value) return;

  submitted.value = true;
  publishError.value = "";

  if (!validate()) {
    formElement.value
      ?.querySelector(`[id="post-${Object.keys(errors)[0]}"]`)
      ?.focus();
    return;
  }

  isPublishing.value = true;

  try {
    await updatePost(route.params.id, {
      title: form.title.trim(),
      description: form.description.trim(),
      restaurantName: form.restaurantName.trim(),
      address: form.address.trim(),
      location: form.location,
      cuisine: form.cuisine,
      priceRange: form.priceRange,
      photos: photos.value.map((photo) =>
        photo.imageId != null ? { id: photo.imageId } : { dataUrl: photo.src },
      ),
    });
  } catch (error) {
    publishError.value = error.message || "Unable to save changes.";
    isPublishing.value = false;
    return;
  }

  await router.push({
    name: "post-detail",
    params: { id: route.params.id },
  });

  isPublishing.value = false;
}

// ── Delete ──────────────────────────────────────────────────
async function handleDelete() {
  if (isPublishing.value) return;

  if (
    !confirm(
      "Delete this post? This cannot be undone. All comments and likes will be removed.",
    )
  ) {
    return;
  }

  isPublishing.value = true;
  publishError.value = "";

  try {
    await deletePost(route.params.id);
  } catch (error) {
    publishError.value = error.message;
    isPublishing.value = false;
    return;
  }

  isPublishing.value = false;
  await router.push({ name: "home" });
}

const headingTitle = computed(() =>
  notFound.value
    ? "Post not found"
    : notOwner.value
      ? "You can't edit this post"
      : "Edit your post",
);

const headingSubtitle = computed(() =>
  notFound.value
    ? "It may have been removed."
    : notOwner.value
      ? "Only the author can edit or delete this post."
      : "Update the details, photos, or delete the post entirely.",
);
</script>

<template>
  <main class="create-post-page">
    <div class="container">
      <div class="row justify-content-center">
        <div>
          <!-- Loading (brief) -->
          <p v-if="!loaded" class="text-muted">Loading post…</p>

          <!-- Not found -->
          <div v-else-if="notFound" class="edit-state">
            <h1>Post not found</h1>
            <p>{{ headingSubtitle }}</p>
            <RouterLink class="publish-button" to="/">
              Back to Discover
            </RouterLink>
          </div>

          <!-- Not owner -->
          <div v-else-if="notOwner" class="edit-state">
            <h1>{{ headingTitle }}</h1>
            <p>{{ headingSubtitle }}</p>
            <RouterLink class="publish-button" to="/"
              >Back to Discover</RouterLink
            >
          </div>

          <!-- Edit form -->
          <template v-else>
            <header class="create-heading">
              <span class="create-tag">Editing</span>
              <h1>{{ headingTitle }}</h1>
              <p>{{ headingSubtitle }}</p>
            </header>

            <form
              ref="formElement"
              class="create-card"
              novalidate
              :aria-busy="isReading || isPublishing"
              @submit.prevent="save"
            >
              <p class="text-muted">All fields are required.</p>

              <div v-if="publishError" class="alert alert-danger" role="alert">
                {{ publishError }}
              </div>

              <p
                v-if="submitted && Object.keys(errors).length"
                class="field-error"
                role="alert"
              >
                Please complete the required fields below.
              </p>

              <!-- Photos -->
              <section class="mb-4" aria-labelledby="photos-title">
                <h2 id="photos-title" class="section-title">
                  Food photos
                  <span class="photo-hint">
                    ({{ photos.length }} / {{ MAX_PHOTOS }})
                  </span>
                </h2>

                <label for="post-photos" class="form-label">Add photos</label>

                <input
                  id="post-photos"
                  ref="fileInput"
                  class="form-control"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  :disabled="
                    isReading || isPublishing || photos.length >= MAX_PHOTOS
                  "
                  :aria-invalid="Boolean(errors.photos || photoError)"
                  aria-describedby="photos-help photos-error"
                  @change="onFileSelected"
                />

                <p id="photos-help" class="form-text">
                  Keep 1–5 photos, up to 500 KB each. The first photo becomes
                  the cover.
                </p>

                <p id="photos-error" class="field-error" aria-live="polite">
                  {{ photoError || errors.photos }}
                </p>

                <p v-if="isReading" role="status">Preparing photos…</p>

                <div class="row g-3 mt-1">
                  <div
                    v-for="(photo, index) in photos"
                    :key="photo.id"
                    class="col-6 col-md-4"
                  >
                    <div class="photo-preview">
                      <img :src="photo.src" :alt="`Photo ${index + 1}`" />

                      <span v-if="index === 0" class="cover-label">Cover</span>

                      <button
                        class="remove-photo"
                        type="button"
                        :disabled="isReading || isPublishing"
                        :aria-label="`Remove photo ${index + 1}`"
                        @click="removePhoto(photo.id)"
                      >
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                      </button>
                    </div>

                    <div class="d-flex gap-2 mt-2">
                      <button
                        class="btn btn-sm btn-outline-dark"
                        type="button"
                        :disabled="index === 0 || isReading || isPublishing"
                        :aria-label="`Move photo ${index + 1} earlier`"
                        @click="movePhoto(index, -1)"
                      >
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                      </button>

                      <button
                        class="btn btn-sm btn-outline-dark"
                        type="button"
                        :disabled="
                          index === photos.length - 1 ||
                          isReading ||
                          isPublishing
                        "
                        :aria-label="`Move photo ${index + 1} later`"
                        @click="movePhoto(index, 1)"
                      >
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Text fields -->
              <div v-for="field in fields" :key="field.name" class="mb-4">
                <label :for="`post-${field.name}`" class="form-label">
                  {{ field.label }}
                </label>

                <textarea
                  v-if="field.multiline"
                  :id="`post-${field.name}`"
                  v-model="form[field.name]"
                  class="form-control"
                  :class="{ 'is-invalid': errors[field.name] }"
                  :placeholder="field.placeholder"
                  :maxlength="field.max"
                  rows="6"
                  required
                  :aria-invalid="Boolean(errors[field.name])"
                  :aria-describedby="`${field.name}-error`"
                  @input="clearError(field.name)"
                ></textarea>

                <input
                  v-else
                  :id="`post-${field.name}`"
                  v-model="form[field.name]"
                  class="form-control"
                  :class="{ 'is-invalid': errors[field.name] }"
                  :placeholder="field.placeholder"
                  :maxlength="field.max"
                  type="text"
                  required
                  :aria-invalid="Boolean(errors[field.name])"
                  :aria-describedby="`${field.name}-error`"
                  @input="clearError(field.name)"
                />

                <p :id="`${field.name}-error`" class="field-error">
                  {{ errors[field.name] }}
                </p>
              </div>

              <!-- Selects -->
              <div class="row g-3">
                <div class="col-12 col-md-4">
                  <label for="post-location" class="form-label">Location</label>
                  <select
                    id="post-location"
                    v-model="form.location"
                    class="form-select"
                    :class="{ 'is-invalid': errors.location }"
                    required
                    :aria-invalid="Boolean(errors.location)"
                    aria-describedby="location-error"
                    @change="clearError('location')"
                  >
                    <option disabled value="">Select location</option>
                    <option v-for="location in locations" :key="location">
                      {{ location }}
                    </option>
                  </select>
                  <p id="location-error" class="field-error">
                    {{ errors.location }}
                  </p>
                </div>

                <div class="col-12 col-md-4">
                  <label for="post-cuisine" class="form-label">Cuisine</label>
                  <select
                    id="post-cuisine"
                    v-model="form.cuisine"
                    class="form-select"
                    :class="{ 'is-invalid': errors.cuisine }"
                    required
                    :aria-invalid="Boolean(errors.cuisine)"
                    aria-describedby="cuisine-error"
                    @change="clearError('cuisine')"
                  >
                    <option disabled value="">Select cuisine</option>
                    <option v-for="cuisine in cuisines" :key="cuisine">
                      {{ cuisine }}
                    </option>
                  </select>
                  <p id="cuisine-error" class="field-error">
                    {{ errors.cuisine }}
                  </p>
                </div>

                <div class="col-12 col-md-4">
                  <label for="post-priceRange" class="form-label">
                    Price range
                  </label>
                  <select
                    id="post-priceRange"
                    v-model="form.priceRange"
                    class="form-select"
                    :class="{ 'is-invalid': errors.priceRange }"
                    required
                    :aria-invalid="Boolean(errors.priceRange)"
                    aria-describedby="price-error"
                    @change="clearError('priceRange')"
                  >
                    <option disabled value="">Select budget</option>
                    <option value="$">$ — Under RM 15</option>
                    <option value="$$">$$ — RM 15–50</option>
                    <option value="$$$">$$$ — Above RM 50</option>
                  </select>
                  <p id="price-error" class="field-error">
                    {{ errors.priceRange }}
                  </p>
                </div>
              </div>

              <!-- Actions -->
              <div class="form-actions edit-actions">
                <button
                  type="button"
                  class="delete-button"
                  :disabled="isPublishing"
                  @click="handleDelete"
                >
                  <i class="bi bi-trash" aria-hidden="true"></i>
                  Delete post
                </button>

                <div class="edit-actions-right">
                  <RouterLink
                    class="cancel-link"
                    :to="`/posts/${route.params.id}`"
                  >
                    Cancel
                  </RouterLink>

                  <button
                    class="publish-button"
                    type="submit"
                    :disabled="isReading || isPublishing"
                  >
                    {{ isPublishing ? "Saving…" : "Save changes" }}
                  </button>
                </div>
              </div>
            </form>
          </template>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped src="../assets/css/create-post.css"></style>

<style scoped>
.edit-state {
  padding: 64px 24px;
  text-align: center;
}

.edit-state h1 {
  margin: 0 0 12px;
  color: var(--text);
  font-size: clamp(1.5rem, 4vw, 2rem);
  font-weight: 800;
}

.edit-state p {
  margin: 0 0 24px;
  color: var(--text);
  opacity: 0.75;
  line-height: 1.6;
}

.edit-state .publish-button {
  display: inline-block;
  text-decoration: none;
}

/* ── Edit-specific actions layout ─────────────────────── */

.edit-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.edit-actions-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 20px;
  margin-left: auto;
}

.delete-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid rgb(205 70 57 / 40%);
  border-radius: 999px;
  background: transparent;
  color: var(--red);
  font-weight: 700;
  cursor: pointer;
  transition:
    background-color 150ms ease,
    color 150ms ease;
}

.delete-button:hover:not(:disabled) {
  background: var(--red);
  color: var(--background);
}

.delete-button:disabled {
  opacity: 0.6;
  cursor: wait;
}

.delete-button:focus-visible {
  outline: 3px solid var(--red);
  outline-offset: 3px;
}

@media (max-width: 575.98px) {
  .edit-actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .edit-actions-right {
    flex-direction: column-reverse;
    margin-left: 0;
    width: 100%;
    gap: 10px;
  }

  .edit-actions-right .cancel-link {
    text-align: center;
  }

  .delete-button,
  .edit-actions-right .publish-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
