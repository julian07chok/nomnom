<!-- src/components/comment/CommentForm.vue -->
<script setup>
import { ref, nextTick} from "vue";
import "../../assets/css/comment-form.css";
import { scrollToFirstError } from "../../utils/scrollToError";

const props = defineProps({
  isLoggedIn: { type: Boolean, default: false },
  submitComment: { type: Function, required: true },
});

const isSubmitting = ref(false);

const text = ref("");
const photos = ref([]);
const error = ref("");
const photoError = ref("");
const isReading = ref(false);
const dragOver = ref(false);
const fileInput = ref(null);

const MAX_LEN = 500;
const MAX_PHOTOS = 3;
const MAX_FILE_SIZE = 500 * 1024;
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"];

function uid() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `c-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function readPhoto(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onerror = () => reject(new Error("Could not read this photo."));

    reader.onload = () => {
      const image = new Image();

      image.onload = () =>
        resolve({
          id: uid(),
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
  addFiles(Array.from(event.dataTransfer?.files || []));
}

function onFileSelected(event) {
  const files = Array.from(event.target.files || []);
  event.target.value = "";
  addFiles(files);
}

async function addFiles(files) {
  if (isReading.value || isSubmitting.value) return;
  if (!files.length) return;

  photoError.value = "";

  if (photos.value.length + files.length > MAX_PHOTOS) {
    photoError.value = `You can attach up to ${MAX_PHOTOS} photos.`;
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
  } catch (err) {
    photoError.value = err.message;
  } finally {
    isReading.value = false;
  }
}

function removePhoto(id) {
  if (isReading.value || isSubmitting.value) return;
  photos.value = photos.value.filter((p) => p.id !== id);
  photoError.value = "";
}

function validate() {
  const trimmed = text.value.trim();

  if (!trimmed && photos.value.length === 0) {
    error.value = "Please write something or attach a photo.";
    return false;
  }

  if (trimmed.length > MAX_LEN) {
    error.value = `Comment must be ${MAX_LEN} characters or fewer.`;
    return false;
  }

  error.value = "";
  return true;
}

async function handleSubmit() {
  if (isReading.value || isSubmitting.value) return;
  if (!validate()) {
    await nextTick();

    scrollToFirstError(
      document.querySelector('.comment-form'),
      ['#comment'],
      { focus: true }
    );
    return;
  }

  isSubmitting.value = true;
  error.value = "";

  try {
    await props.submitComment({
      text: text.value.trim(),
      images: photos.value.map((photo) => photo.src),
    });

    text.value = "";
    photos.value = [];
    photoError.value = "";
  } catch (err) {
    error.value = err.message || "Unable to post your comment.";
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <div class="comment-form-wrap">
    <p v-if="!isLoggedIn" class="comment-form-login">
      <RouterLink to="/login">Log in</RouterLink>
      to leave a comment.
    </p>

    <form v-else class="comment-form" @submit.prevent="handleSubmit">
      <label for="comment" class="comment-label">Add a comment</label>

      <!-- Compact input with icon at TOP-RIGHT -->
      <div
        class="comment-input-wrap"
        :class="{ 'is-dragging': dragOver }"
        @dragover.prevent="onDragOver"
        @dragenter.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop"
      >
        <textarea
          id="comment"
          v-model="text"
          class="comment-input"
          rows="1"
          :maxlength="MAX_LEN"
          placeholder="Share your thoughts…"
          aria-describedby="comment-help"
          :disabled="isSubmitting"
        ></textarea>

        <button
          type="button"
          class="comment-attach-btn"
          :class="{ 'is-busy': isReading }"
          :aria-label="`Attach photos (up to ${MAX_PHOTOS})`"
          :title="`Attach photos (up to ${MAX_PHOTOS})`"
          :disabled="isReading || isSubmitting || photos.length >= MAX_PHOTOS"
          @click="pickFile"
        >
          <i
            :class="
              isReading
                ? 'bi bi-arrow-repeat spin'
                : photos.length >= MAX_PHOTOS
                  ? 'bi bi-image-fill'
                  : 'bi bi-image'
            "
            aria-hidden="true"
          ></i>
        </button>

        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="visually-hidden"
          :disabled="isReading || isSubmitting"
          @change="onFileSelected"
        />
      </div>

      <p id="comment-help" class="comment-help">
        <span v-if="photos.length">
          {{ photos.length }} / {{ MAX_PHOTOS }} photos ·
        </span>
        {{ text.length }} / {{ MAX_LEN }}
      </p>

      <p v-if="photoError" class="comment-error" role="alert">
        {{ photoError }}
      </p>

      <ul v-if="photos.length" class="comment-pending-list">
        <li
          v-for="(photo, index) in photos"
          :key="photo.id"
          class="comment-pending-item"
        >
          <img :src="photo.src" :alt="`Attached photo ${index + 1}`" />
          <button
            type="button"
            class="comment-pending-remove"
            :aria-label="`Remove photo ${index + 1}`"
            :disabled="isReading || isSubmitting"
            @click="removePhoto(photo.id)"
          >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </li>
      </ul>

      <p v-if="error" class="comment-error" role="alert">{{ error }}</p>

      <button
        type="submit"
        class="comment-submit"
        :disabled="isReading || isSubmitting"
      >
        {{
          isSubmitting ? "Posting…" : isReading ? "Preparing…" : "Post comment"
        }}
      </button>
    </form>
  </div>
</template>
