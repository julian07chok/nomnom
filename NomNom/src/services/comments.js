import { apiRequest } from "./api";
import { useAuth } from "./auth";

function resolveImageUrl(url) {
  if (typeof url !== "string") return "";

  return url.startsWith("api/") ? `${import.meta.env.BASE_URL}${url}` : url;
}

function normalizeComments(data) {
  return data.comments.map((comment) => ({
    ...comment,
    userAvatar: comment.userAvatar.startsWith("api/")
      ? `${import.meta.env.BASE_URL}${comment.userAvatar}`
      : comment.userAvatar,
    userAvatar: resolveImageUrl(comment.userAvatar),
    images: comment.images.map(resolveImageUrl),
  }));
}

export async function getComments(postId) {
  const data = await apiRequest(
    `comments.php?post_id=${encodeURIComponent(postId)}`,
  );

  return normalizeComments(data);
}

async function writeComments(endpoint, method, body) {
  const { initAuth, isLoggedIn } = useAuth();
  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in to comment.");
  }

  const data = await apiRequest(endpoint, { method, body });
  return normalizeComments(data);
}

export function createComment(postId, comment) {
  return writeComments(
    `comments.php?post_id=${encodeURIComponent(postId)}`,
    "POST",
    {
      text: comment.text,
      images: comment.images,
    },
  );
}

export function updateComment(id, text) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "PUT", {
    text,
  });
}

export function deleteComment(id) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "DELETE");
}
