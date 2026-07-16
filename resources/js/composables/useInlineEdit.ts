import { reactive } from 'vue'

type InlineEditEntry = { value: string }

export function useInlineEdit() {
  const edits = reactive<Record<string, InlineEditEntry>>({})

  function startEdit(id: string, currentValue: string) {
    edits[id] = { value: currentValue }
  }

  function cancelEdit(id: string) {
    delete edits[id]
  }

  function isEditing(id: string) {
    return id in edits
  }

  function getValue(id: string) {
    return edits[id]?.value ?? ''
  }

  function setValue(id: string, value: string) {
    if (edits[id]) edits[id].value = value
  }

  return { edits, startEdit, cancelEdit, isEditing, getValue, setValue }
}
