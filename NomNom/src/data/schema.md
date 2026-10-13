# NomNom Data Model

## User
| Field | Type | Notes |
|-------|------|-------|
| id | number | unique |
| username | string | unique, used in URL |
| email | string | unique |
| password | string | plain in mock; hash in backend |
| avatar | string | URL |
| bio | string | short text |
| postIds | number[] | posts authored by user |
| savedPostIds | number[] | posts bookmarked |
| likedPostIds | number[] | posts liked |
| createdAt | string | ISO date |

## Post
| Field | Type | Notes |
|-------|------|-------|
| id | number | unique |
| title | string | |
| description | string | long text |
| restaurantName | string | |
| address | string | |
| cuisine | string | one of CUISINES list |
| priceRange | string | "$", "$$", "$$$" |
| location | string | city/area |
| coverImage | string | URL |
| gallery | string[] | 1–5 image URLs |
| authorId | number | FK → User.id |
| authorName | string | denormalised for display |
| authorAvatar | string | denormalised |
| likes | number | total like count |
| likedByMe | boolean | client-side flag |
| savedByMe | boolean | client-side flag |
| createdAt | string | ISO date |
| commentIds | number[] | FK → Comment.id |

## Comment
| Field | Type | Notes |
|-------|------|-------|
| id | number | unique |
| postId | number | FK → Post.id |
| userId | number | FK → User.id |
| userName | string | denormalised |
| userAvatar | string | denormalised |
| text | string | |
| images | string[] | 0–3 image URLs |
| createdAt | string | ISO date |

## Enums

### CUISINES
Malaysian, Chinese, Indian, Japanese, Korean, Thai, Vietnamese,
Italian, Western, Cafe, Dessert, Fusion, Middle Eastern

### PRICE_RANGES
"$"    → Budget (under RM 15)
"$$"   → Mid-range (RM 15–50)
"$$$"  → Premium (above RM 50)

### LOCATIONS
Kuala Lumpur, Petaling Jaya, Subang Jaya, Cyberjaya, Penang,
Johor Bahru, Melaka, Ipoh