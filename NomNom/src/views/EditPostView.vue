<script setup>
import { computed, nextTick, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import { usePosts } from "../services/posts";
import { geocodeAddress } from "../utils/geo";
import { scrollToFirstError } from "../utils/scrollToError";
import LocationPicker from "../components/post/LocationPicker.vue";
import AppButton from "../components/common/AppButton.vue";

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

// ── Map / Geocoding state ───────────────────────────────────
const latitude = ref(null);
const longitude = ref(null);
const isGeocoding = ref(false);
const geocodeError = ref("");
const mapSection = ref(null);
const geocodeErrorEl = ref(null);

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

// ── Auto-clear geocode error when address changes ──────────
watch(
  () => form.address,
  () => {
    if (geocodeError.value) {
      geocodeError.value = "";
    }
  },
);

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

  // Prefill coordinates from the existing post (may be null)
  latitude.value =
    found.latitude != null ? Number(found.latitude) : null;
  longitude.value =
    found.longitude != null ? Number(found.longitude) : null;

  photos.value = found.galleryItems.map((image, index) => ({
    id: uid(),
    imageId: image.id,
    name: `photo-${index + 1}`,
    src: image.url,
  }));

  submitted.value = false;
  publishError.value = "";
  photoError.value = "";
  geocodeError.value = "";
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

// ── Geocode address → map marker ───────────────────────────
async function findAddressOnMap() {
  if (isGeocoding.value) return;

  const address = form.address.trim();
  if (!address) return;

  isGeocoding.value = true;
  geocodeError.value = "";

  try {
    const result = await geocodeAddress(address);

    if (!result) {
      geocodeError.value =
        "Couldn't find that address. Try adding a city or landmark.";

      await nextTick();
      geocodeErrorEl.value?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
      geocodeErrorEl.value?.focus?.({ preventScroll: true });
      return;
    }

    latitude.value = Number(result.lat.toFixed(6));
    longitude.value = Number(result.lng.toFixed(6));

    await nextTick();

    mapSection.value?.focus?.({ preventScroll: true });
    mapSection.value?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  } catch (error) {
    geocodeError.value =
      error.message || "Address lookup failed. Please try again.";

    await nextTick();
    geocodeErrorEl.value?.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
    geocodeErrorEl.value?.focus?.({ preventScroll: true });
  } finally {
    setTimeout(() => {
      isGeocoding.value = false;
    }, 800);
  }
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

  // Coordinates are required
  if (latitude.value == null || longitude.value == null) {
    errors.coordinates =
      "Please pin the restaurant's location on the map.";
  }

  return Object.keys(errors).length === 0;
}

// ── Save ────────────────────────────────────────────────────
async function save() {
  if (isReading.value || isPublishing.value) return;

  submitted.value = true;
  publishError.value = "";

  if (!validate()) {
    await nextTick();

    scrollToFirstError(
      formElement.value,
      [
        '#post-title',
        '#post-description',
        '#post-restaurantName',
        '#post-address',
        '#post-location',
        '#post-cuisine',
        '#post-priceRange',
        '.drop-zone',
        '#edit-map-section',
      ],
      { focus: true }
    );
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
      latitude: latitude.value,
      longitude: longitude.value,
      photos: photos.value.map((photo) =>
        photo.imageId != null ? { id: photo.imageId } : { dataUrl: photo.src },
      ),
    });
  } catch (error) {
    publishError.value = error.message || "Unable to save changes.";
    isPublishing.value = false;

    await nextTick();
    scrollToFirstError(
      formElement.value,
      ['.alert-danger'],
      { focus: false }
    );
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
            <RouterLink class="publish-button" to="/">Back to Discover</RouterLink>
          </div>

          <!-- Edit form -->
          <template v-else>
            <header class="create-heading">
              <span class="create-tag">Editing</span>
              <h1>{{ headingTitle }}</h1>
              <p>{{ headingSubtitle }}</p>
            </header>

            <form ref="formElement" class="create-card" novalidate :aria-busy="isReading || isPublishing"
              @submit.prevent="save">
              <p class="text-muted">All fields are required.</p>

              <div v-if="publishError" class="alert alert-danger" role="alert">
                {{ publishError }}
              </div>

              <p v-if="submitted && Object.keys(errors).length" class="field-error" role="alert">
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

                <input id="post-photos" ref="fileInput" class="form-control" type="file"
                  accept="image/jpeg,image/png,image/webp" multiple :disabled="isReading || isPublishing || photos.length >= MAX_PHOTOS
                    " :aria-invalid="Boolean(errors.photos || photoError)" aria-describedby="photos-help photos-error"
                  @change="onFileSelected" />

                <p id="photos-help" class="form-text">
                  Keep 1–5 photos, up to 500 KB each. The first photo becomes
                  the cover.
                </p>

                <p id="photos-error" class="field-error" aria-live="polite">
                  {{ photoError || errors.photos }}
                </p>

                <p v-if="isReading" role="status">Preparing photos…</p>

                <div class="row g-3 mt-1">
                  <div v-for="(photo, index) in photos" :key="photo.id" class="col-6 col-md-4">
                    <div class="photo-preview">
                      <img :src="photo.src" :alt="`Photo ${index + 1}`" />

                      <span v-if="index === 0" class="cover-label">Cover</span>

                      <button class="remove-photo" type="button" :disabled="isReading || isPublishing"
                        :aria-label="`Remove photo ${index + 1}`" @click="removePhoto(photo.id)">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                      </button>
                    </div>

                    <div class="d-flex gap-2 mt-2">
                      <button class="btn btn-sm btn-outline-dark" type="button"
                        :disabled="index === 0 || isReading || isPublishing"
                        :aria-label="`Move photo ${index + 1} earlier`" @click="movePhoto(index, -1)">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                      </button>

                      <button class="btn btn-sm btn-outline-dark" type="button" :disabled="index === photos.length - 1 ||
                        isReading ||
                        isPublishing
                        " :aria-label="`Move photo ${index + 1} later`" @click="movePhoto(index, 1)">
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

                <textarea v-if="field.multiline" :id="`post-${field.name}`" v-model="form[field.name]"
                  class="form-control" :class="{ 'is-invalid': errors[field.name] }" :placeholder="field.placeholder"
                  :maxlength="field.max" rows="6" required :aria-invalid="Boolean(errors[field.name])"
                  :aria-describedby="`${field.name}-error`" @input="clearError(field.name)"></textarea>

                <input v-else :id="`post-${field.name}`" v-model="form[field.name]" class="form-control"
                  :class="{ 'is-invalid': errors[field.name] }" :placeholder="field.placeholder" :maxlength="field.max"
                  type="text" required :aria-invalid="Boolean(errors[field.name])"
                  :aria-describedby="`${field.name}-error`" @input="clearError(field.name)" />

                <!-- Address-only: Find on map button + geocode error -->
                <template v-if="field.name === 'address'">
                  <button type="button" class="find-on-map-btn" :disabled="isGeocoding || !form.address.trim()"
                    @click="findAddressOnMap">
                    <i :class="isGeocoding ? 'bi bi-arrow-repeat spin' : 'bi bi-geo-alt'" aria-hidden="true"></i>
                    {{ isGeocoding ? "Finding…" : "Find on map" }}
                  </button>

                  <p v-if="geocodeError" :ref="(el) => (geocodeErrorEl.value = el)" class="field-error geocode-error"
                    role="alert">
                    {{ geocodeError }}
                  </p>
                </template>

                <p :id="`${field.name}-error`" class="field-error">
                  {{ errors[field.name] }}
                </p>
              </div>

              <!-- Map + Lat/Lng -->
              <div id="edit-map-section" ref="mapSection" class="create-map-section" tabindex="-1">
                <LocationPicker v-model:latitude="latitude" v-model:longitude="longitude" />
                <p v-if="errors.coordinates" class="field-error">
                  {{ errors.coordinates }}
                </p>
              </div>

              <!-- Selects -->
              <div class="row g-3">
                <div class="col-12 col-md-4">
                  <label for="post-location" class="form-label">Location</label>
                  <select id="post-location" v-model="form.location" class="form-select"
                    :class="{ 'is-invalid': errors.location }" required :aria-invalid="Boolean(errors.location)"
                    aria-describedby="location-error" @change="clearError('location')">
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
                  <select id="post-cuisine" v-model="form.cuisine" class="form-select"
                    :class="{ 'is-invalid': errors.cuisine }" required :aria-invalid="Boolean(errors.cuisine)"
                    aria-describedby="cuisine-error" @change="clearError('cuisine')">
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
                  <select id="post-priceRange" v-model="form.priceRange" class="form-select"
                    :class="{ 'is-invalid': errors.priceRange }" required :aria-invalid="Boolean(errors.priceRange)"
                    aria-describedby="price-error" @change="clearError('priceRange')">
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
                <AppButton variant="danger" mobile-block :disabled="isPublishing || isReading" @click="handleDelete">
                  <i class="bi bi-trash" aria-hidden="true"></i>
                  Delete post
                </AppButton>

                <div class="edit-actions-right">
                  <RouterLink class="cancel-link" :to="`/posts/${route.params.id}`">
                    Cancel
                  </RouterLink>

                  <AppButton type="submit" mobile-block :disabled="isReading" :loading="isPublishing"
                    loading-text="Saving…">
                    Save changes
                  </AppButton>
                </div>
              </div>
            </form>
          </template>
        </div>
      </div>
    </div>
    <ConfirmDialog ref="feedbackDialog" />
  </main>
</template>

<style scoped src="../assets/css/create-post.css"></style>
<style scoped src="../assets/css/edit-post.css"></style>
