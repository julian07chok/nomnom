<script setup>
import { ref } from 'vue'

const menuOpen = ref(false)

function closeMenu() {
  menuOpen.value = false
}
</script>

<template>
  <header class="site-header">
    <nav class="navbar-inner" aria-label="Main navigation">
      <RouterLink class="brand" to="/" @click="closeMenu">
        <span class="brand-icon" aria-hidden="true">✦</span>
        NomNom<span class="brand-dot">.</span>
      </RouterLink>

      <button
        class="menu-toggle"
        type="button"
        :aria-expanded="menuOpen"
        aria-controls="main-menu"
        :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
        @click="menuOpen = !menuOpen"
      >
        <span aria-hidden="true">{{ menuOpen ? '✕' : '☰' }}</span>
      </button>

      <div
        id="main-menu"
        class="nav-menu"
        :class="{ 'is-open': menuOpen }"
        @keydown.esc="closeMenu"
      >
        <RouterLink
          class="nav-link"
          to="/"
          exact-active-class="is-active"
          @click="closeMenu"
        >
          Discover
        </RouterLink>

        <RouterLink
          class="nav-link"
          to="/saved"
          active-class="is-active"
          @click="closeMenu"
        >
          Saved places
        </RouterLink>

        <RouterLink
          class="login-link"
          to="/login"
          @click="closeMenu"
        >
          Log in
          <span aria-hidden="true">↗</span>
        </RouterLink>
      </div>
    </nav>
  </header>
</template>

<style scoped>
.site-header {
  background: #fffcf3;
  border-bottom: 1px solid rgb(72 57 52 / 12%);
}

.navbar-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 18px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.brand {
  display: inline-flex;
  align-items: center;
  color: #483934;
  font-size: 1.8rem;
  font-weight: 800;
  letter-spacing: -1px;
  text-decoration: none;
}

.brand-icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  margin-right: 10px;
  border-radius: 12px;
  background: #ffd77d;
  font-size: 1.5rem;
}

.brand-dot {
  color: #f1613e;
}

.menu-toggle {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid rgb(72 57 52 / 20%);
  border-radius: 12px;
  background: transparent;
  color: #483934;
  font-size: 1.3rem;
  cursor: pointer;
}

.nav-menu {
  display: none;
  width: 100%;
  flex-direction: column;
  gap: 8px;
}

.nav-menu.is-open {
  display: flex;
}

.nav-link,
.login-link {
  padding: 12px 18px;
  border-radius: 999px;
  color: #483934;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 150ms ease;
}

.nav-link:hover {
  background: #f4f2c2;
}

.nav-link.is-active {
  background: #dce9fc;
}

.login-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  background: #483934;
  color: #fffcf3;
}

.login-link:hover {
  background: #cd4639;
}

a:focus-visible,
button:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 4px;
}

@media (min-width: 768px) {
  .menu-toggle {
    display: none;
  }

  .nav-menu {
    display: flex;
    width: auto;
    flex-direction: row;
    align-items: center;
    gap: 12px;
  }
}

@media (min-width: 1200px) {
  .navbar-inner {
    padding: 22px 32px;
  }
}
</style>