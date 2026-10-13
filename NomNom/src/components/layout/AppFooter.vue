<script setup>
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-top">
        <div class="footer-intro">
          <RouterLink class="footer-brand" to="/">
            NomNom<span>.</span>
          </RouterLink>

          <p>
            Good food. Shared discoveries.
            Find your next favourite bite.
          </p>

          <span class="footer-tag">Made for food lovers</span>
        </div>

        <nav class="footer-links" aria-label="Footer navigation">
          <h2>Explore</h2>
          <RouterLink to="/">Discover food</RouterLink>
          <RouterLink to="/saved">Saved places</RouterLink>
        </nav>

        <nav class="footer-links" aria-label="Account navigation">
          <h2>Join the table</h2>
          <RouterLink to="/register">Create an account</RouterLink>
          <RouterLink to="/login">Log in</RouterLink>
        </nav>
      </div>

      <div class="footer-bottom">
        <small>© {{ currentYear }} NomNom.</small>
        <span>A little hungry, always curious.</span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  background: #dce9fc;
  color: #483934;
  border-top: 1px solid rgb(72 57 52 / 10%);
}

.footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 24px 20px;
}

.footer-top {
  display: grid;
  grid-template-columns: 1fr;
  gap: 32px;
  padding-bottom: 32px;
}

.footer-brand {
  color: #483934;
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -1px;
  text-decoration: none;
}

.footer-brand span {
  color: #cd4639;
}

.footer-intro p {
  max-width: 300px;
  margin: 12px 0 18px;
  line-height: 1.7;
}

.footer-tag {
  display: inline-block;
  padding: 8px 14px;
  border-radius: 999px;
  background: #ffb6bf;
  font-size: 0.85rem;
  font-weight: 600;
}

.footer-links {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
}

.footer-links h2 {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 700;
}

.footer-links a {
  color: #483934;
  line-height: 1.5;
  text-decoration: none;
}

.footer-links a:hover {
  text-decoration: underline;
  text-underline-offset: 4px;
}

.footer-links a:focus-visible,
.footer-brand:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 4px;
  border-radius: 4px;
}

.footer-bottom {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 20px;
  border-top: 1px solid rgb(72 57 52 / 18%);
  font-size: 0.85rem;
}

.footer-bottom small {
  font-size: inherit;
}

@media (min-width: 768px) {
  .footer-top {
    grid-template-columns: 2fr 1fr 1fr;
  }

  .footer-bottom {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

@media (min-width: 1200px) {
  .footer-inner {
    padding: 48px 32px 24px;
  }
}
</style>