import { defineConfig } from 'vite';

const PORT = 3001;

export default defineConfig(({ command }) => ({
	base: command === 'serve' ? '' : '/dist/',
	build: {
		outDir: 'src/dist',
		emptyOutDir: true,
		manifest: true,
		// In Vite, source maps are configured here (not in rollup output)
		sourcemap: true,
		rollupOptions: {
			input: {
				main: 'src/main.js',
			},
		},
	},
	server: {
		fs: { strict: false },
		host: '0.0.0.0',
		port: PORT,
		strictPort: true,
		origin: `http://localhost:${PORT}`,
	},
}));
