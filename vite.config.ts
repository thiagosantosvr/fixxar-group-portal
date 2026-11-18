import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// Relative asset paths keep the build portable between DreamHost and a GitHub Pages preview.
export default defineConfig({ base: "./", plugins: [react()] });
