<script setup>
import { useRoute } from "vue-router";
import { useAuth } from "../../services/auth";
import UserAvatar from "../user/UserAvatar.vue";

const route = useRoute();
const { currentUser, isLoggedIn } = useAuth();

// Highlight Profile when visiting account-related pages
const isProfileActive = () =>
  ["my-profile", "edit-profile", "saved"].includes(route.name);
</script>

<template>
  <!-- TOP HEADER -->
  <header class="site-header">
    <nav class="navbar-inner" aria-label="Desktop navigation">
      <!-- Logo: visible on all screen sizes -->
      <RouterLink class="brand" to="/">
        <span class="brand-icon" aria-hidden="true">✦</span>
        NomNom<span class="brand-dot">.</span>
      </RouterLink>

      <!-- DESKTOP NAVIGATION -->
      <div class="nav-menu">
        <RouterLink class="nav-link" to="/" exact-active-class="is-active">
          Discover
        </RouterLink>

        <RouterLink class="nav-link" to="/search" active-class="is-active">
          Explore
        </RouterLink>

        <RouterLink class="nav-link" to="/create" active-class="is-active">
          Create post
        </RouterLink>

        <RouterLink
          v-if="isLoggedIn"
          class="profile-avatar-link"
          to="/profile"
          aria-label="Go to my profile"
          :title="currentUser.username"
        >
          <UserAvatar :src="currentUser.avatar" :size="38" />
        </RouterLink>

        <RouterLink v-else class="login-link" to="/login">
          Log in
          <span aria-hidden="true">↗</span>
        </RouterLink>
      </div>
    </nav>
  </header>

  <!-- MOBILE BOTTOM NAVIGATION -->
  <nav class="mobile-bottom-nav" aria-label="Mobile main navigation">
    <!-- 1. Discover -->
    <RouterLink
      to="/"
      class="mobile-bottom-link"
      :class="{ 'is-active': route.name === 'home' }"
      :aria-current="route.name === 'home' ? 'page' : undefined"
    >
      <i class="bi bi-house-door" aria-hidden="true"></i>
      <span>Discover</span>
    </RouterLink>

    <!-- 2. Explore (was Search, moved up) -->
    <RouterLink
      to="/search"
      class="mobile-bottom-link"
      :class="{ 'is-active': route.name === 'search' }"
      :aria-current="route.name === 'search' ? 'page' : undefined"
    >
      <i class="bi bi-search" aria-hidden="true"></i>
      <span>Explore</span>
    </RouterLink>

    <!-- 3. Create (was 2nd, now 3rd) -->
    <RouterLink
      to="/create"
      class="mobile-bottom-link"
      :class="{ 'is-active': route.name === 'create-post' }"
      :aria-current="route.name === 'create-post' ? 'page' : undefined"
    >
      <i class="bi bi-plus-square" aria-hidden="true"></i>
      <span>Create</span>
    </RouterLink>

    <!-- 4. Profile (unchanged) -->
    <RouterLink
      to="/profile"
      class="mobile-bottom-link"
      :class="{ 'is-active': isProfileActive() }"
      :aria-current="route.name === 'my-profile' ? 'page' : undefined"
    >
      <UserAvatar v-if="isLoggedIn" :src="currentUser.avatar" :size="26" />
      <i v-else class="bi bi-person-circle" aria-hidden="true"></i>
      <span>Profile</span>
    </RouterLink>
  </nav>
</template>

<style scoped src="../../assets/css/appnavbar.css"></style>