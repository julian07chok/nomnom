<script setup>
import { computed, nextTick, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../services/auth";

import UserAvatar from "../components/user/UserAvatar.vue";

const router = useRouter();
const { currentUser, updateProfile } = useAuth();

// ── Form state ─────────────────────────────────────────────
const displayName = ref(currentUser.value?.displayName || "");
const bio = ref(currentUser.value?.bio || "");
const avatar = ref(currentUser.value?.avatar || "");

const submitted = ref(false);
const isSaving = ref(false);
const saveError = ref("");
const savedSuccessfully = ref(false);

// File-upload state
const fileInput = ref(null);
const dragOver = ref(false);
const isReadingFile = ref(false);
const fileError = ref("");

const displayNameInput = ref(null);
const bioInput = ref(null);
const avatarUrlInput = ref(null);

const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"];

// ── Validation ─────────────────────────────────────────────
const displayNameError = computed(() => {
  if (!submitted.value) return "";
  const value = displayName.value.trim();
  if (!value) return "Please enter a display name.";
  if (value.length < 2) return "Display name must be at least 2 characters.";
  if (value.length > 50) return "Display name must be 50 characters or fewer.";
  return "";
});

const bioError = computed(() => {
  if (!submitted.value) return "";
  if (bio.value.length > 500) return "Bio must be 500 characters or fewer.";
  return "";
});

const hasErrors = computed(
  () => Boolean(displayNameError.value || bioError.value),
);

// ── File handling ──────────────────────────────────────────
function pickFile() {
  fileInput.value?.click();
}

function onFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = ""; // allow re-selecting the same file
  if (file) handleFile(file);
}

function onDrop(event) {
  dragOver.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file) handleFile(file);
}

function onDragOver() {
  dragOver.value = true;
}

function onDragLeave() {
  dragOver.value = false;
}

function handleFile(file) {
  fileError.value = "";
  saveError.value = "";

  if (!ALLOWED_TYPES.includes(file.type)) {
    fileError.value = "Please use a JPG, PNG, or WebP image.";
    return;
  }

  if (file.size > MAX_FILE_SIZE) {
    fileError.value = "Image must be under 2 MB.";
    return;
  }

  isReadingFile.value = true;

  const reader = new FileReader();

  reader.onerror = () => {
    fileError.value = "Could not read this file. Try another image.";
    isReadingFile.value = false;
  };

  reader.onload = () => {
    // Validate it's actually an image (guard against renamed files)
    const img = new Image();
    img.onload = () => {
      avatar.value = reader.result; // data URL
      isReadingFile.value = false;
    };
    img.onerror = () => {
      fileError.value = "That file isn't a valid image.";
      isReadingFile.value = false;
    };
    img.src = reader.result;
  };

  reader.readAsDataURL(file);
}

function removePhoto() {
  avatar.value = "";
  fileError.value = "";
}

// ── Focus first invalid field ──────────────────────────────
async function focusFirstError() {
  await nextTick();
  if (displayNameError.value) displayNameInput.value?.focus();
  else if (bioError.value) bioInput.value?.focus();
}

// ── Submit ─────────────────────────────────────────────────
async function handleSave() {
  if (isSaving.value || isReadingFile.value) return;

  submitted.value = true;
  saveError.value = "";
  savedSuccessfully.value = false;

  if (hasErrors.value) {
    await focusFirstError();
    return;
  }

  isSaving.value = true;

  try {
    await updateProfile({
      displayName: displayName.value,
      bio: bio.value,
      avatar: avatar.value, // either a data URL, a URL, or ""
    });
  } catch (error) {
    // localStorage quota errors will surface here for large data URLs
    saveError.value =
      error.message || "Unable to save changes. Please try again.";
    isSaving.value = false;
    return;
  }

  isSaving.value = false;
  savedSuccessfully.value = true;

  setTimeout(() => {
    router.push({ name: "my-profile" });
  }, 800);
}

function handleCancel() {
  router.push({ name: "my-profile" });
}
</script>

<template>
  <main v-if="currentUser" class="edit-profile-page">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
          <header class="edit-heading">
            <span class="edit-tag">Your account</span>
            <h1>Edit profile</h1>
            <p>Update how you appear to other NomNom food lovers.</p>
          </header>

          <form
            class="edit-card"
            novalidate
            :aria-busy="isSaving || isReadingFile"
            @submit.prevent="handleSave"
          >
            <div v-if="saveError" class="edit-error" role="alert">
              {{ saveError }}
            </div>

            <div
              v-if="savedSuccessfully"
              class="edit-success"
              role="status"
            >
              Profile updated.
            </div>

            <!-- ==========================================
                 AVATAR — drag/drop or click to upload
                 ========================================== -->
            <section class="avatar-section">
              <!-- Preview column -->
              <div class="avatar-preview">
                <UserAvatar :src="avatar" :size="120" />

                <button
                  v-if="avatar"
                  type="button"
                  class="remove-avatar-btn"
                  :disabled="isSaving"
                  @click="removePhoto"
                >
                  <i class="bi bi-trash" aria-hidden="true"></i>
                  Remove
                </button>
              </div>

              <!-- Upload column -->
              <div class="avatar-fields">
                <label class="form-label">Profile picture</label>

                <div
                  class="drop-zone"
                  :class="{ 'is-dragging': dragOver }"
                  role="button"
                  tabindex="0"
                  :aria-label="
                    avatar
                      ? 'Drop a new image or press Enter to choose a file'
                      : 'Drop an image or press Enter to choose a file'
                  "
                  @click="pickFile"
                  @keydown.enter.prevent="pickFile"
                  @keydown.space.prevent="pickFile"
                  @dragover.prevent="onDragOver"
                  @dragenter.prevent="onDragOver"
                  @dragleave.prevent="onDragLeave"
                  @drop.prevent="onDrop"
                >
                  <i
                    :class="
                      isReadingFile
                        ? 'bi bi-arrow-repeat spin'
                        : 'bi bi-cloud-arrow-up'
                    "
                    aria-hidden="true"
                  ></i>

                  <p class="drop-title">
                    <template v-if="isReadingFile">
                      Preparing your image…
                    </template>
                    <template v-else-if="dragOver">
                      Drop your image here
                    </template>
                    <template v-else>
                      Drag an image here, or
                      <span class="drop-link">browse files</span>
                    </template>
                  </p>

                  <p class="drop-hint">JPG, PNG, or WebP · up to 2 MB</p>

                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="visually-hidden"
                    :disabled="isSaving || isReadingFile"
                    @change="onFileSelected"
                  />
                </div>

                <p v-if="fileError" class="field-error" role="alert">
                  {{ fileError }}
                </p>

                <!-- Advanced: paste URL instead -->
                <details class="advanced-url">
                  <summary>Or paste an image URL</summary>
                  <input
                    id="profile-avatar-url"
                    ref="avatarUrlInput"
                    v-model="avatar"
                    class="form-control mt-2"
                    type="url"
                    placeholder="https://i.pravatar.cc/150?img=1"
                    :disabled="isSaving || isReadingFile"
                    @input="
                      saveError = '';
                      fileError = '';
                    "
                  />
                </details>
              </div>
            </section>

            <!-- Display name -->
            <div class="mb-4">
              <label for="profile-display-name" class="form-label">
                Display name
              </label>
              <input
                id="profile-display-name"
                ref="displayNameInput"
                v-model="displayName"
                class="form-control"
                :class="{ 'is-invalid': displayNameError }"
                type="text"
                placeholder="How should we call you?"
                maxlength="50"
                required
                :aria-invalid="Boolean(displayNameError)"
                :aria-describedby="
                  displayNameError ? 'display-name-error' : undefined
                "
                @input="saveError = ''"
              />
              <p
                v-if="displayNameError"
                id="display-name-error"
                class="field-error"
              >
                {{ displayNameError }}
              </p>
            </div>

            <!-- Bio -->
            <div class="mb-4">
              <label for="profile-bio" class="form-label">Bio</label>
              <textarea
                id="profile-bio"
                ref="bioInput"
                v-model="bio"
                class="form-control"
                :class="{ 'is-invalid': bioError }"
                rows="4"
                maxlength="500"
                placeholder="Tell other food lovers about yourself…"
                :aria-invalid="Boolean(bioError)"
                :aria-describedby="bioError ? 'bio-error' : 'bio-help'"
                @input="saveError = ''"
              ></textarea>
              <p v-if="bioError" id="bio-error" class="field-error">
                {{ bioError }}
              </p>
              <p v-else id="bio-help" class="field-help">
                {{ bio.length }} / 500 characters
              </p>
            </div>

            <!-- Username (read-only) -->
            <div class="mb-4">
              <label class="form-label">Username</label>
              <p class="username-readonly">
                @{{ currentUser.username }}
                <span class="username-note">(cannot be changed)</span>
              </p>
            </div>

            <!-- Actions -->
            <div class="form-actions">
              <button
                type="button"
                class="btn-cancel"
                :disabled="isSaving || isReadingFile"
                @click="handleCancel"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn-save"
                :disabled="isSaving || isReadingFile"
              >
                {{ isSaving ? "Saving…" : "Save changes" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.edit-profile-page {
  padding: 48px 0;
  background: linear-gradient(180deg, var(--light-blue), var(--background));
  color: var(--text);
}

.edit-heading {
  margin-bottom: 28px;
}

.edit-tag {
  display: inline-block;
  margin-bottom: 16px;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--yellow);
  font-size: 0.85rem;
  font-weight: 700;
}

.edit-heading h1 {
  margin-bottom: 8px;
  font-size: clamp(1.75rem, 3.5vw, 2.5rem);
  font-weight: 800;
  letter-spacing: -0.5px;
}

.edit-heading p {
  margin: 0;
  line-height: 1.6;
}

.edit-card {
  padding: 28px 24px;
  border: 1px solid rgb(72 57 52 / 15%);
  border-radius: 24px;
  background: var(--background);
  box-shadow: 0 12px 36px rgb(72 57 52 / 8%);
}

.edit-error {
  margin-bottom: 20px;
  padding: 12px 16px;
  border: 1px solid var(--red);
  border-radius: 12px;
  color: var(--red);
}

.edit-success {
  margin-bottom: 20px;
  padding: 12px 16px;
  border: 1px solid #2b8a3e;
  border-radius: 12px;
  color: #2b8a3e;
  font-weight: 600;
}

/* ── Avatar section ───────────────────────────────────── */

.avatar-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  margin-bottom: 28px;
  padding-bottom: 28px;
  border-bottom: 1px solid rgb(72 57 52 / 12%);
}

.avatar-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.remove-avatar-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid rgb(205 70 57 / 40%);
  border-radius: 999px;
  background: transparent;
  color: var(--red);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.remove-avatar-btn:hover {
  background: var(--red);
  color: var(--background);
}

.avatar-fields {
  flex: 1;
  width: 100%;
  min-width: 0;
}

/* ── Drop zone ────────────────────────────────────────── */

.drop-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 28px 16px;
  margin-top: 8px;
  border: 2px dashed rgb(72 57 52 / 25%);
  border-radius: 16px;
  background: rgb(244 242 194 / 40%);
  text-align: center;
  cursor: pointer;
  transition:
    border-color 150ms ease,
    background-color 150ms ease,
    transform 120ms ease;
}

.drop-zone:hover,
.drop-zone:focus-visible {
  border-color: var(--text);
  background: rgb(244 242 194 / 70%);
}

.drop-zone.is-dragging {
  border-color: var(--red);
  background: rgb(255 182 191 / 30%);
  transform: scale(1.01);
}

.drop-zone > i {
  font-size: 2rem;
  color: var(--text);
  opacity: 0.75;
}

.drop-zone .spin {
  animation: spin 800ms linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.drop-title {
  margin: 4px 0 0;
  font-weight: 600;
  color: var(--text);
}

.drop-link {
  color: var(--red);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.drop-hint {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.65;
}

/* ── Advanced URL input ───────────────────────────────── */

.advanced-url {
  margin-top: 12px;
  font-size: 0.85rem;
}

.advanced-url summary {
  color: var(--text);
  opacity: 0.75;
  cursor: pointer;
  user-select: none;
}

.advanced-url summary:hover {
  opacity: 1;
}

/* ── Form controls ────────────────────────────────────── */

.form-label {
  font-weight: 600;
}

.form-control {
  min-height: 48px;
  padding: 12px 14px;
  border: 1px solid rgb(72 57 52 / 30%);
  border-radius: 12px;
  background-color: var(--background);
  color: var(--text);
}

.form-control:focus {
  border-color: var(--text);
  background-color: var(--background);
  color: var(--text);
  box-shadow: 0 0 0 3px rgb(173 202 218 / 45%);
}

.field-error {
  margin: 8px 0 0;
  color: var(--red);
  font-size: 0.875rem;
}

.field-help {
  margin: 8px 0 0;
  font-size: 0.8rem;
  opacity: 0.65;
}

/* ── Username reference ───────────────────────────────── */

.username-readonly {
  margin: 4px 0 0;
  padding: 12px 14px;
  border: 1px dashed rgb(72 57 52 / 25%);
  border-radius: 12px;
  background: rgb(72 57 52 / 4%);
  font-weight: 600;
  overflow-wrap: anywhere;
}

.username-note {
  color: rgb(72 57 52 / 60%);
  font-weight: 400;
  font-size: 0.85rem;
}

/* ── Form actions ─────────────────────────────────────── */

.form-actions {
  display: flex;
  flex-direction: column-reverse;
  gap: 10px;
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid rgb(72 57 52 / 12%);
}

.btn-cancel,
.btn-save {
  min-height: 48px;
  padding: 12px 20px;
  border-radius: 999px;
  font-weight: 700;
  cursor: pointer;
}

.btn-cancel {
  border: 1px solid rgb(72 57 52 / 30%);
  background: transparent;
  color: var(--text);
}

.btn-cancel:hover {
  background: var(--light-yellow);
}

.btn-save {
  border: none;
  background: var(--text);
  color: var(--background);
}

.btn-save:hover {
  background: var(--red);
}

.btn-save:disabled,
.btn-cancel:disabled {
  opacity: 0.6;
  cursor: wait;
}

button:focus-visible,
input:focus-visible,
textarea:focus-visible,
.drop-zone:focus-visible {
  outline: 3px solid var(--red);
  outline-offset: 3px;
}

/* ── Responsive ───────────────────────────────────────── */

@media (min-width: 576px) {
  .form-actions {
    flex-direction: row;
    justify-content: flex-end;
  }

  .btn-cancel {
    min-width: 120px;
  }

  .btn-save {
    min-width: 160px;
  }
}

@media (min-width: 768px) {
  .edit-profile-page {
    padding: 72px 0;
  }

  .edit-card {
    padding: 36px;
  }

  .avatar-section {
    flex-direction: row;
    align-items: flex-start;
  }

  .avatar-fields {
    padding-top: 4px;
  }
}

@media (min-width: 992px) {
  .edit-profile-page {
    padding: 96px 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .drop-zone,
  .drop-zone .spin {
    transition: none;
    animation: none;
  }
}
</style>