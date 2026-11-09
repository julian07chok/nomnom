<script setup>
import { computed, nextTick, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../services/auth";

const route = useRoute();
const router = useRouter();
const { register } = useAuth();

// ── Form state ──────────────────────────────────────────────
const username = ref("");
const email = ref("");
const password = ref("");
const confirmPassword = ref("");

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const acceptedTerms = ref(false);

const submitted = ref(false);
const isSubmitting = ref(false);
const registerError = ref("");

// Refs for focus management on invalid submit
const usernameInput = ref(null);
const emailInput = ref(null);
const passwordInput = ref(null);
const confirmInput = ref(null);
const termsInput = ref(null);

// ── Validation (gated by `submitted` so users aren't nagged while typing) ──
const usernameError = computed(() => {
  if (!submitted.value) return "";
  const value = username.value.trim();

  if (!value) return "Please choose a username.";
  if (value.length < 3) return "Username must be at least 3 characters.";
  if (value.length > 20) return "Username must be 20 characters or fewer.";
  if (!/^[a-zA-Z0-9_]+$/.test(value)) {
    return "Only letters, numbers, and underscores are allowed.";
  }
  return "";
});

const emailError = computed(() => {
  if (!submitted.value) return "";
  const value = email.value.trim();

  if (!value) return "Please enter your email address.";
  if (value.length > 190) {
    return "Email must be 190 characters or fewer.";
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    return "Please enter a valid email address.";
  }
  return "";
});

const passwordError = computed(() => {
  if (!submitted.value) return "";
  const value = password.value;

  if (!value) return "Please create a password.";
  if (value.length < 8) return "Password must be at least 8 characters.";
  if (new TextEncoder().encode(value).length > 72) {
    return "Password must be 72 bytes or fewer.";
  }

  if (value.includes("\0")) {
    return "Password contains an unsupported character.";
  }
  if (!/[A-Za-z]/.test(value) || !/[0-9]/.test(value)) {
    return "Password must include both letters and numbers.";
  }
  return "";
});

const confirmPasswordError = computed(() => {
  if (!submitted.value) return "";
  if (!confirmPassword.value) return "Please confirm your password.";
  if (confirmPassword.value !== password.value) {
    return "Passwords do not match.";
  }
  return "";
});

const termsError = computed(() => {
  if (!submitted.value) return "";
  if (!acceptedTerms.value) {
    return "You must accept the terms to create an account.";
  }
  return "";
});

const hasErrors = computed(() =>
  Boolean(
    usernameError.value ||
    emailError.value ||
    passwordError.value ||
    confirmPasswordError.value ||
    termsError.value,
  ),
);

// ── Redirect handling (mirrors LoginView) ───────────────────
const loginTarget = computed(() => ({
  name: "login",
  query:
    typeof route.query.redirect === "string"
      ? { redirect: route.query.redirect }
      : {},
}));

function getDestination() {
  const redirect = route.query.redirect;

  if (
    typeof redirect !== "string" ||
    !redirect.startsWith("/") ||
    redirect.startsWith("//") ||
    redirect.includes("\\")
  ) {
    return "/";
  }

  const destination = router.resolve(redirect);

  if (
    !destination.matched.length ||
    ["login", "register", "not-found"].includes(destination.name)
  ) {
    return "/";
  }

  return destination.fullPath;
}

// ── Focus first invalid field ───────────────────────────────
async function focusFirstError() {
  await nextTick();

  if (usernameError.value) usernameInput.value?.focus();
  else if (emailError.value) emailInput.value?.focus();
  else if (passwordError.value) passwordInput.value?.focus();
  else if (confirmPasswordError.value) confirmInput.value?.focus();
  else if (termsError.value) termsInput.value?.focus();
}

// ── Submit ──────────────────────────────────────────────────
async function handleRegister() {
  if (isSubmitting.value) return;

  submitted.value = true;
  registerError.value = "";

  if (hasErrors.value) {
    await focusFirstError();
    return;
  }

  isSubmitting.value = true;

  try {
    await register({
      username: username.value,
      email: email.value,
      password: password.value,
      confirmPassword: confirmPassword.value,
      acceptedTerms: acceptedTerms.value,
    });
  } catch (error) {
    registerError.value =
      error.message || "Unable to create account. Please try again.";

    // Send focus to the field the server complained about
    await nextTick();
    if (/email/i.test(registerError.value)) emailInput.value?.focus();
    else if (/username/i.test(registerError.value))
      usernameInput.value?.focus();

    isSubmitting.value = false;
    return;
  }

  isSubmitting.value = false;
  await router.replace(getDestination());
}
</script>

<template>
  <main class="register-page">
    <div class="container">
      <div class="row justify-content-center align-items-center g-5">
        <section class="col-lg-5 register-intro">
          <span class="intro-tag">Join the table</span>
          <h1>Your next favourite bite is one sign-up away.</h1>
          <p>
            Create your free NomNom account to save dishes, share reviews, and
            follow food lovers across Malaysia.
          </p>
        </section>

        <section class="col-12 col-md-8 col-lg-5">
          <div class="register-card" aria-labelledby="register-title">
            <h2 id="register-title">Create your account</h2>
            <p class="card-subtitle">It only takes a minute.</p>

            <form
              novalidate
              :aria-busy="isSubmitting"
              @submit.prevent="handleRegister"
            >
              <div v-if="registerError" class="register-error" role="alert">
                {{ registerError }}
              </div>

              <!-- Username -->
              <div class="mb-3">
                <label for="register-username" class="form-label">
                  Username
                </label>
                <input
                  id="register-username"
                  ref="usernameInput"
                  v-model="username"
                  class="form-control"
                  :class="{ 'is-invalid': usernameError }"
                  type="text"
                  autocomplete="username"
                  placeholder="e.g. hungryfoodie"
                  maxlength="20"
                  required
                  :aria-invalid="Boolean(usernameError)"
                  :aria-describedby="
                    usernameError ? 'username-error' : 'username-help'
                  "
                  @input="registerError = ''"
                />
                <p v-if="usernameError" id="username-error" class="field-error">
                  {{ usernameError }}
                </p>
                <p v-else id="username-help" class="field-help">
                  3–20 characters. Letters, numbers, underscores.
                </p>
              </div>

              <!-- Email -->
              <div class="mb-3">
                <label for="register-email" class="form-label">
                  Email address
                </label>
                <input
                  id="register-email"
                  ref="emailInput"
                  v-model="email"
                  class="form-control"
                  :class="{ 'is-invalid': emailError }"
                  type="email"
                  autocomplete="email"
                  placeholder="you@example.com"
                  required
                  :aria-invalid="Boolean(emailError)"
                  :aria-describedby="
                    emailError ? 'register-email-error' : undefined
                  "
                  @input="registerError = ''"
                />
                <p
                  v-if="emailError"
                  id="register-email-error"
                  class="field-error"
                >
                  {{ emailError }}
                </p>
              </div>

              <!-- Password -->
              <div class="mb-3">
                <label for="register-password" class="form-label">
                  Password
                </label>

                <div class="password-field">
                  <input
                    id="register-password"
                    ref="passwordInput"
                    v-model="password"
                    class="form-control"
                    :class="{ 'is-invalid': passwordError }"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                    required
                    :aria-invalid="Boolean(passwordError)"
                    :aria-describedby="
                      passwordError ? 'password-error' : 'password-help'
                    "
                    @input="registerError = ''"
                  />
                  <button
                    class="password-toggle"
                    type="button"
                    aria-controls="register-password"
                    :aria-label="
                      showPassword ? 'Hide password' : 'Show password'
                    "
                    :aria-pressed="showPassword"
                    @click="showPassword = !showPassword"
                  >
                    <i
                      :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"
                      aria-hidden="true"
                    ></i>
                  </button>
                </div>

                <p v-if="passwordError" id="password-error" class="field-error">
                  {{ passwordError }}
                </p>
                <p v-else id="password-help" class="field-help">
                  Must include both letters and numbers.
                </p>
              </div>

              <!-- Confirm Password -->
              <div class="mb-3">
                <label for="register-confirm" class="form-label">
                  Confirm password
                </label>

                <div class="password-field">
                  <input
                    id="register-confirm"
                    ref="confirmInput"
                    v-model="confirmPassword"
                    class="form-control"
                    :class="{ 'is-invalid': confirmPasswordError }"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    placeholder="Re-enter your password"
                    required
                    :aria-invalid="Boolean(confirmPasswordError)"
                    :aria-describedby="
                      confirmPasswordError ? 'confirm-error' : undefined
                    "
                    @input="registerError = ''"
                  />
                  <button
                    class="password-toggle"
                    type="button"
                    aria-controls="register-confirm"
                    :aria-label="
                      showConfirmPassword ? 'Hide password' : 'Show password'
                    "
                    :aria-pressed="showConfirmPassword"
                    @click="showConfirmPassword = !showConfirmPassword"
                  >
                    <i
                      :class="
                        showConfirmPassword ? 'bi bi-eye-slash' : 'bi bi-eye'
                      "
                      aria-hidden="true"
                    ></i>
                  </button>
                </div>

                <p
                  v-if="confirmPasswordError"
                  id="confirm-error"
                  class="field-error"
                >
                  {{ confirmPasswordError }}
                </p>
              </div>

              <!-- Terms -->
              <div class="form-check mb-4">
                <input
                  id="register-terms"
                  ref="termsInput"
                  v-model="acceptedTerms"
                  class="form-check-input"
                  :class="{ 'is-invalid': termsError }"
                  type="checkbox"
                  :aria-invalid="Boolean(termsError)"
                  :aria-describedby="termsError ? 'terms-error' : undefined"
                  @change="registerError = ''"
                />
                <label for="register-terms" class="form-check-label">
                  I agree to the
                  <a href="#" @click.prevent>Terms of Service</a>
                  and
                  <a href="#" @click.prevent>Privacy Policy</a>.
                </label>
                <p v-if="termsError" id="terms-error" class="field-error">
                  {{ termsError }}
                </p>
              </div>

              <button
                class="submit-button"
                type="submit"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? "Creating account…" : "Create account" }}
              </button>
            </form>

            <p class="login-prompt">
              Already have an account?
              <RouterLink :to="loginTarget">Log in</RouterLink>
            </p>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

<style scoped>
.register-page {
  padding: 48px 0;
  background: linear-gradient(180deg, var(--light-blue), var(--background));
  color: var(--text);
}

.intro-tag {
  display: inline-block;
  margin-bottom: 20px;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--yellow);
  font-size: 0.85rem;
  font-weight: 700;
}

.register-intro h1 {
  max-width: 480px;
  margin-bottom: 20px;
  font-size: clamp(2rem, 4vw, 3.25rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -1px;
}

.register-intro p {
  max-width: 440px;
  margin: 0;
  line-height: 1.75;
}

.register-card {
  padding: 28px 24px;
  border: 1px solid rgb(72 57 52 / 15%);
  border-radius: 24px;
  background: var(--background);
  box-shadow: 0 12px 36px rgb(72 57 52 / 8%);
}

.register-card h2 {
  margin-bottom: 8px;
  font-size: 1.8rem;
  font-weight: 800;
}

.card-subtitle {
  margin-bottom: 24px;
}

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

.password-field {
  position: relative;
}

.password-field .form-control {
  width: 100%;
  padding-right: 52px;
}

.password-field .form-control.is-invalid {
  background-image: none;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 6px;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--text);
  font-size: 1.2rem;
  cursor: pointer;
}

.password-toggle:hover {
  background: var(--light-yellow);
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

.register-error {
  margin-bottom: 20px;
  padding: 12px 16px;
  border: 1px solid var(--red);
  border-radius: 12px;
  color: var(--red);
}

.form-check-label {
  font-size: 0.9rem;
}

.form-check-label a {
  color: var(--red);
  font-weight: 600;
}

.submit-button {
  width: 100%;
  min-height: 48px;
  padding: 12px 20px;
  border: none;
  border-radius: 999px;
  background: var(--text);
  color: var(--background);
  font-weight: 700;
}

.submit-button:hover {
  background: var(--red);
}

.submit-button:disabled {
  opacity: 0.65;
  cursor: wait;
}

.login-prompt {
  margin: 24px 0 0;
  text-align: center;
}

.login-prompt a {
  color: var(--red);
  font-weight: 700;
}

button:focus-visible,
a:focus-visible,
input:focus-visible {
  outline: 3px solid var(--red);
  outline-offset: 3px;
}

@media (min-width: 768px) {
  .register-page {
    padding: 72px 0;
  }

  .register-card {
    padding: 36px;
  }
}

@media (min-width: 992px) {
  .register-page {
    padding: 96px 0;
  }
}
</style>
