<script setup>
import { computed, nextTick, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import AppButton from "../components/common/AppButton.vue";
import UserAvatar from "../components/user/UserAvatar.vue";
import ConfirmDialog from "../components/common/ConfirmDialog.vue";

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
const feedbackDialog = ref(null);

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

const hasErrors = computed(() =>
  Boolean(displayNameError.value || bioError.value),
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
  if (isSaving.value || isReadingFile.value) return;
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
  if (isSaving.value || isReadingFile.value) return;
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
    saveError.value =
      error.message || "Unable to save changes. Please try again.";
    isSaving.value = false;
    return;
  }

  savedSuccessfully.value = true;

  const acknowledged = await feedbackDialog.value.open({
    title: "Profile updated",
    message: "Your profile changes have been saved successfully.",
    confirmText: "View profile",
    showCancel: false,
  });

  if (acknowledged === null) return;

  await router.push({ name: "my-profile" });

  isSaving.value = false;
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
                :disabled="isSaving"
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
                :disabled="isSaving"
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
              <AppButton
                variant="secondary"
                mobile-block
                :disabled="isSaving || isReadingFile"
                @click="handleCancel"
              >
                Cancel
              </AppButton>

              <AppButton
                type="submit"
                mobile-block
                :disabled="isReadingFile"
                :loading="isSaving"
                loading-text="Saving…"
              >
                Save changes
              </AppButton>
            </div>
          </form>
        </div>
      </div>
    </div>
    <ConfirmDialog ref="feedbackDialog" />
  </main>
</template>

<style scoped src="../assets/css/edit-profile.css"></style>
