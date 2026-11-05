<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import { usePosts } from "../services/posts";

const router = useRouter();
const { currentUser } = useAuth();
const { createPost } = usePosts();

const formElement = ref(null);
const photos = ref([]);
const photoError = ref("");
const publishError = ref("");
const isReading = ref(false);
const isPublishing = ref(false);
const submitted = ref(false);

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

function readPhoto(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onerror = () => reject(new Error("Could not read this photo."));

    reader.onload = () => {
      const image = new Image();

      image.onload = () =>
        resolve({
          id: crypto.randomUUID(),
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

async function selectPhotos(event) {
  const files = Array.from(event.target.files || []);
  event.target.value = "";

  if (!files.length) return;

  photoError.value = "";
  publishError.value = "";

  if (photos.value.length + files.length > MAX_PHOTOS) {
    photoError.value = "You can add up to five photos.";
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
  photos.value = photos.value.filter((photo) => photo.id !== id);
  photoError.value = "";
  publishError.value = "";
}

function clearError(name) {
  delete errors[name];
  publishError.value = "";
}

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

async function publish() {
  if (isReading.value || isPublishing.value) return;

  submitted.value = true;
  publishError.value = "";

  if (!validate()) {
    formElement.value
      ?.querySelector(`[id="post-${Object.keys(errors)[0]}"]`)
      ?.focus();
    return;
  }

  if (!currentUser.value) {
    await router.push({
      name: "login",
      query: { redirect: "/create" },
    });
    return;
  }

  isPublishing.value = true;

  try {
    createPost(
      {
        title: form.title.trim(),
        description: form.description.trim(),
        restaurantName: form.restaurantName.trim(),
        address: form.address.trim(),
        location: form.location,
        cuisine: form.cuisine,
        priceRange: form.priceRange,
        coverImage: photos.value[0].src,
        gallery: photos.value.map((photo) => photo.src),
      },
      currentUser.value,
    );
  } catch (error) {
    publishError.value = error.message;
    isPublishing.value = false;
    return;
  }

  await router.push({ name: "home" });
  isPublishing.value = false;
}
</script>

<template>
  <main class="create-post-page">
    <div class="container">
      <div class="row justify-content-center">
        <div>
          <header class="create-heading">
            <span class="create-tag">Share your discovery</span>
            <h1>What’s worth a bite?</h1>
            <p>
              Share your photos and tell the community about your experience.
            </p>
          </header>

          <form
            ref="formElement"
            class="create-card"
            novalidate
            :aria-busy="isReading || isPublishing"
            @submit.prevent="publish"
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

            <section class="mb-4" aria-labelledby="photos-title">
              <h2 id="photos-title" class="section-title">
                <label for="post-photos">
                  Food photos <span class="photo-hint">(Add 1–5 photos)</span>
                </label>
              </h2>

              <input
                id="post-photos"
                class="form-control"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                :disabled="isReading || isPublishing"
                :aria-invalid="Boolean(errors.photos || photoError)"
                aria-describedby="photos-help photos-error"
                @change="selectPhotos"
              />

              <p id="photos-help" class="form-text">
                JPG, PNG, or WebP, up to 500 KB each. Your first photo becomes
                the cover.
              </p>

              <p id="photos-error" class="field-error" aria-live="polite">
                {{ photoError || errors.photos }}
              </p>

              <p v-if="isReading" role="status">Preparing your photos…</p>

              <div v-if="photos.length" class="row g-3 mt-1">
                <div
                  v-for="(photo, index) in photos"
                  :key="photo.id"
                  class="col-6 col-md-4"
                >
                  <div class="photo-preview">
                    <img
                      :src="photo.src"
                      :alt="`Selected photo ${index + 1}: ${photo.name}`"
                    />

                    <span v-if="index === 0" class="cover-label">Cover</span>

                    <button
                      class="remove-photo"
                      type="button"
                      :aria-label="`Remove photo ${index + 1}`"
                      :disabled="isReading || isPublishing"
                      @click="removePhoto(photo.id)"
                    >
                      <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
              </div>
            </section>

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

            <div class="form-actions">
              <RouterLink class="cancel-link" to="/">Cancel</RouterLink>

              <button
                class="publish-button"
                type="submit"
                :disabled="isReading || isPublishing"
              >
                {{ isPublishing ? "Publishing…" : "Publish post" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped src="../assets/css/create-post.css"></style>
