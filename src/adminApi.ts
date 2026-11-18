export type AdminUser = { id: number; name: string; email: string; role: string };
export type ApiSession = { authenticated: boolean; user?: AdminUser; csrfToken?: string };
export type ApiBootstrap = {
  pages: Array<{ slug: string; content: { headline?: string; subheadline?: string; intro?: string } }>;
  universes: Array<{ slug: string; name: string; eyebrow: string; description: string; detail: string; category: string; website_url?: string | null; status: "active" | "in-development" | "archived"; sort_order: number }>;
  messages: Array<{ id: number; name: string; email: string; subject: string; message: string; status: "new" | "in_progress" | "done" | "archived"; created_at: string }>;
  settings: Array<{ setting_key: string; value: unknown }>;
};
let csrfToken = "";
async function request<T>(path: string, method = "GET", payload?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (payload !== undefined) headers["Content-Type"] = "application/json";
  if (csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(`/api${path}`, {
    method, headers, credentials: "same-origin",
    ...(payload !== undefined ? { body: JSON.stringify(payload) } : {})
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(result.message || result.error || `HTTP ${response.status}`);
    Object.assign(error, { status: response.status, code: result.error });
    throw error;
  }
  return result as T;
}
export async function getAdminSession() {
  const result = await request<ApiSession>("/auth/session");
  csrfToken = result.csrfToken || "";
  return result;
}
export async function loginAdmin(email: string, password: string) {
  const result = await request<{ user: AdminUser; csrfToken: string }>("/auth/login", "POST", { email, password });
  csrfToken = result.csrfToken;
  return result.user;
}
export async function logoutAdmin() {
  await request("/auth/logout", "POST", {});
  csrfToken = "";
}
export const loadAdminData = () => request<ApiBootstrap>("/admin/bootstrap");
export const saveHomePage = (data: { headline: string; subheadline: string; intro: string }) => request("/admin/pages/home", "PATCH", data);
export const saveBrand = (slug: string, data: Record<string, unknown>) => request(`/admin/universes/${encodeURIComponent(slug)}`, "PUT", data);
export const updateContactStatus = (id: number, status: string) => request(`/admin/messages/${id}`, "PATCH", { status });
export const saveSiteSettings = (data: Record<string, unknown>) => request("/admin/settings", "PATCH", data);

export const publishHomePage = () => request('/admin/pages/home/publish', 'POST', {});

export type AdminMediaAsset = { id: number; original_name: string; storage_name: string; mime_type: string; file_size: number; alt_text: string; created_at: string };
export async function uploadAdminMedia(file: File) {
  const form = new FormData();
  form.append("file", file);
  form.append("alt_text", file.name.replace(/\\.[^.]+$/, "").replace(/[-_]+/g, " "));
  const headers: Record<string,string> = { Accept: "application/json" };
  if (csrfToken) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch("/api/admin/media", { method: "POST", headers, credentials: "same-origin", body: form });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) { const error = new Error(result.message || result.error || `HTTP ${response.status}`); Object.assign(error,{status:response.status,code:result.error}); throw error; }
  return result as { ok: boolean; asset: AdminMediaAsset & { url: string } };
}
export async function deleteAdminMedia(id: number) { return request(`/admin/media/${id}`, "DELETE"); }
