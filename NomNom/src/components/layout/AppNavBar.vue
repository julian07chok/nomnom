<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../services/auth";
import UserAvatar from "../user/UserAvatar.vue";

const menuOpen = ref(false);
const drawer = ref(null);
const router = useRouter();
const { currentUser, isLoggedIn, logout } = useAuth();

function handleLogout() {
  closeMenu();
  logout();
  router.push("/");
}

let previousOverflow = "";
let desktopQuery;

function openMenu() {
  if (menuOpen.value || desktopQuery?.matches) return;

  previousOverflow = document.body.style.overflow;
  document.body.style.overflow = "hidden";
  drawer.value.showModal();
  menuOpen.value = true;
}

function closeMenu() {
  drawer.value?.close();
}

function onMenuClosed() {
  document.body.style.overflow = previousOverflow;
  menuOpen.value = false;
}

function onBreakpointChange(event) {
  if (event.matches && menuOpen.value) {
    closeMenu();
  }
}

onMounted(() => {
  desktopQuery = window.matchMedia("(min-width: 768px)");
  desktopQuery.addEventListener("change", onBreakpointChange);
});

onBeforeUnmount(() => {
  desktopQuery?.removeEventListener("change", onBreakpointChange);

  if (menuOpen.value) {
    document.body.style.overflow = previousOverflow;
  }
});
</script>

<template>
  <header class="site-header">
    <nav class="navbar-inner" aria-label="Main navigation">
      <RouterLink class="brand" to="/">
        <span class="brand-icon" aria-hidden="true">✦</span>
        NomNom<span class="brand-dot">.</span>
      </RouterLink>

      <!-- Mobile menu button -->
      <button
        class="menu-toggle"
        type="button"
        :aria-expanded="menuOpen"
        aria-controls="mobile-menu"
        aria-haspopup="dialog"
        aria-label="Open navigation menu"
        @click="openMenu"
      >
        <span aria-hidden="true">☰</span>
      </button>

      <!-- Desktop navigation -->
      <div class="nav-menu">
        <RouterLink class="nav-link" to="/" exact-active-class="is-active">
          Discover
        </RouterLink>

        <RouterLink
          class="nav-link"
          to="/search"
          active-class="is-active"
          @click="closeMenu"
        >
          Search
        </RouterLink>

        <RouterLink class="nav-link" to="/saved" active-class="is-active">
          Saved places
        </RouterLink>

        <template v-if="isLoggedIn">
          <RouterLink
            class="profile-avatar-link"
            to="/profile"
            aria-label="Go to my profile"
            :title="currentUser.username"
          >
            <UserAvatar :src="currentUser.avatar" :size="38" />
          </RouterLink>
        </template>

        <RouterLink v-else class="login-link" to="/login">
          Log in
          <span aria-hidden="true">↗</span>
        </RouterLink>
      </div>
    </nav>
  </header>

  <!-- Mobile drawer -->
  <dialog
    id="mobile-menu"
    ref="drawer"
    class="mobile-drawer"
    aria-labelledby="drawer-title"
    @close="onMenuClosed"
    @click.self="closeMenu"
  >
    <div class="drawer-panel">
      <div class="drawer-header">
        <h2 id="drawer-title">Explore NomNom</h2>

        <button
          class="drawer-close"
          type="button"
          aria-label="Close navigation menu"
          autofocus
          @click="closeMenu"
        >
          <span aria-hidden="true">✕</span>
        </button>
      </div>

      <nav class="drawer-links" aria-label="Mobile navigation">
        <RouterLink
          class="nav-link"
          to="/"
          exact-active-class="is-active"
          @click="closeMenu"
        >
          <i class="bi bi-compass" aria-hidden="true"></i>
          Discover
        </RouterLink>

        <RouterLink
          class="nav-link"
          to="/saved"
          active-class="is-active"
          @click="closeMenu"
        >
          <i class="bi bi-bookmark" aria-hidden="true"></i>
          Saved places
        </RouterLink>

        <template v-if="isLoggedIn">
          <RouterLink class="nav-link" to="/profile" @click="closeMenu">
            <i class="bi bi-person" aria-hidden="true"></i>
            My profile
          </RouterLink>
        </template>
      </nav>

      <div class="drawer-bottom">
        <button
          v-if="isLoggedIn"
          class="login-link logout-button"
          type="button"
          @click="handleLogout"
        >
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
          Log out
        </button>

        <RouterLink v-else class="login-link" to="/login" @click="closeMenu">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
          Log in
        </RouterLink>
      </div>
    </div>
  </dialog>
</template>

<style scoped src="../../assets/css/appnavbar.css"></style>
