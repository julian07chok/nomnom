<script setup>
import { computed, nextTick, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../services/auth";

const route = useRoute();
const router = useRouter();
const { login } = useAuth();

const email = ref("");
const password = ref("");
const showPassword = ref(false);
const submitted = ref(false);
const isSubmitting = ref(false);
const loginError = ref("");
const emailInput = ref(null);
const passwordInput = ref(null);

const emailError = computed(() => {
  if (!submitted.value) return "";
  if (!email.value.trim()) return "Please enter your email address.";

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    return "Please enter a valid email address.";
  }

  return "";
});

const passwordError = computed(() => {
  if (!submitted.value) return "";
  if (!password.value) return "Please enter your password.";
  return "";
});

const registerTarget = computed(() => ({
  name: "register",
  query:
    typeof route.query.redirect === "string"
      ? { redirect: route.query.redirect }
      : {},
}));

function getDestination() {
  const redirect = route.query.redirect;

  // Accept only known routes within this app.
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

async function handleLogin() {
  if (isSubmitting.value) return;

  submitted.value = true;
  loginError.value = "";

  if (emailError.value || passwordError.value) {
    await nextTick();

    if (emailError.value) {
      emailInput.value?.focus();
    } else {
      passwordInput.value?.focus();
    }

    return;
  }

  isSubmitting.value = true;

  try {
    await login(email.value, password.value);
  } catch (error) {
    loginError.value = error.message || "Unable to log in. Please try again.";
    isSubmitting.value = false;
    return;
  }

  isSubmitting.value = false;
  await router.replace(getDestination());
}
</script>

<template>
  <main class="login-page">
    <div class="container">
      <div class="row justify-content-center align-items-center g-5">
        <section class="col-lg-5 login-intro">
          <span class="intro-tag">Your next favourite bite awaits</span>
          <h1>Good food tastes better when shared.</h1>
          <p>
            Log in to save your discoveries, share your food experiences, and
            join the conversation.
          </p>
        </section>

        <section class="col-12 col-md-8 col-lg-5">
          <div class="login-card" aria-labelledby="login-title">
            <h2 id="login-title">Welcome back</h2>
            <p class="card-subtitle">Log in to your NomNom account.</p>

            <form
              novalidate
              :aria-busy="isSubmitting"
              @submit.prevent="handleLogin"
            >
              <div v-if="loginError" class="login-error" role="alert">
                {{ loginError }}
              </div>

              <div class="mb-4">
                <label for="login-email" class="form-label">
                  Email address
                </label>
                <input
                  id="login-email"
                  ref="emailInput"
                  v-model="email"
                  class="form-control"
                  :class="{ 'is-invalid': emailError }"
                  type="email"
                  autocomplete="username"
                  placeholder="you@example.com"
                  required
                  :aria-invalid="Boolean(emailError)"
                  :aria-describedby="emailError ? 'email-error' : undefined"
                  @input="loginError = ''"
                />
                <p v-if="emailError" id="email-error" class="field-error">
                  {{ emailError }}
                </p>
              </div>

              <div class="mb-4">
                <label for="login-password" class="form-label">
                  Password
                </label>

                <div class="password-field">
                  <input
                    id="login-password"
                    ref="passwordInput"
                    v-model="password"
                    class="form-control"
                    :class="{ 'is-invalid': passwordError }"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    required
                    :aria-invalid="Boolean(passwordError)"
                    :aria-describedby="
                      passwordError ? 'password-error' : undefined
                    "
                    @input="loginError = ''"
                  />

                  <button
                    class="password-toggle"
                    type="button"
                    aria-controls="login-password"
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
              </div>

              <button
                class="submit-button"
                type="submit"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? "Logging in…" : "Log in" }}
              </button>
            </form>

            <p class="register-prompt">
              New to NomNom?
              <RouterLink :to="registerTarget">Create an account</RouterLink>
            </p>

            <aside class="demo-note" aria-label="Demo account">
              <strong>Try the demo account</strong>
              <span>Email: julian@example.com</span>
              <span>Password: password123</span>
              <small>Demo login only. Use these sample credentials.</small>
            </aside>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

<style scoped>
.login-page {
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

.login-intro h1 {
  max-width: 480px;
  margin-bottom: 20px;
  font-size: clamp(2rem, 4vw, 3.25rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -1px;
}

.login-intro p {
  max-width: 440px;
  margin: 0;
  line-height: 1.75;
}

.login-card {
  padding: 28px 24px;
  border: 1px solid rgb(72 57 52 / 15%);
  border-radius: 24px;
  background: var(--background);
  box-shadow: 0 12px 36px rgb(72 57 52 / 8%);
}

.login-card h2 {
  margin-bottom: 8px;
  font-size: 1.8rem;
  font-weight: 800;
}

.card-subtitle {
  margin-bottom: 28px;
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

/* Hide Bootstrap's validation icon to avoid overlapping the eye. */
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

.login-error {
  margin-bottom: 20px;
  padding: 12px 16px;
  border: 1px solid var(--red);
  border-radius: 12px;
  color: var(--red);
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

.register-prompt {
  margin: 24px 0;
  text-align: center;
}

.register-prompt a {
  color: var(--red);
  font-weight: 700;
}

.demo-note {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  background: var(--light-yellow);
  overflow-wrap: anywhere;
  font-size: 0.9rem;
}

.demo-note small {
  margin-top: 4px;
}

button:focus-visible,
a:focus-visible {
  outline: 3px solid var(--red);
  outline-offset: 3px;
}

@media (min-width: 768px) {
  .login-page {
    padding: 72px 0;
  }

  .login-card {
    padding: 36px;
  }
}

@media (min-width: 992px) {
  .login-page {
    padding: 96px 0;
  }
}
</style>
