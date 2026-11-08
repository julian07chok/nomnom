# NomNom.

A community food journal for discovering, sharing, and saving restaurant experiences across Malaysia.

**Live demo:** _[Add your Mercury URL here when deployed]_

---

## Table of Contents

- [About](#about)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Demo Account](#demo-account)
- [Deployment](#deployment)
- [Data Model](#data-model)
- [Browser Support](#browser-support)
- [Accessibility](#accessibility)
- [Known Limitations](#known-limitations)
- [Authors](#authors)
- [License](#license)

---

## About

NomNom is a Vue 3 single-page application where food lovers can share restaurant discoveries with photos and honest reviews, save favourites for future trips, and explore what others are eating around the country.

The project was built as part of **COS30043 – Interface Design and Development** at Swinburne University of Technology.

---

## Features

### Core

- **Browse & Discover** — a photo-based feed of food and restaurant posts, with cuisine filters and pagination
- **Search & Filter** — search by keyword and filter by cuisine, location, and price range; sort by newest or most liked
- **Post Details** — full photo gallery, restaurant info, author details, likes, save, and threaded comments
- **Create Post** — upload 1–5 photos with drag-and-drop, reorder to pick a cover, and add a title, description, restaurant name, address, cuisine, location, and price range
- **Edit Post** — update any field, add or remove photos, or delete the post through a confirmation dialog
- **Like & Save** — like posts and bookmark them for later; both persist across sessions
- **Comments** — leave comments on posts and delete your own

### Accounts

- **Register** — create an account with username, email, and password
- **Login** — with validation, password visibility toggle, and redirect back to the page you were trying to reach
- **My Profile** — view your avatar, bio, published posts, and saved posts in tabbed views
- **Edit Profile** — change your avatar (upload or paste a URL), display name, and bio
- **User Profiles** — view other users' public profiles and their published posts

### User Roles

- **Guests** can browse posts, search, view profiles, and read comments
- **Logged-in users** can additionally create posts, comment, like, save, and manage their own posts and profile

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Vue 3 (Composition API with `<script setup>`) |
| Build tool | Vite |
| Router | Vue Router 4 |
| Styling | Bootstrap 5 + custom CSS (mobile-first) |
| Icons | Bootstrap Icons |
| Pagination | vuejs-paginate-next |
| Persistence | `localStorage` (mock — will migrate to PHP + MySQL) |