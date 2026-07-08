declare module '@fontsource/inter'
declare module 'vuedraggable'
declare module 'pinia-plugin-persistedstate' {
  const piniaPluginPersistedstate: (context: unknown) => void
  export default piniaPluginPersistedstate
}
declare module 'vitest' {
  export type Mock<T = unknown> = T
}
