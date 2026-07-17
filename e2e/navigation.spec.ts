import { expect, test } from '@playwright/test'

test.describe('Navigation (Super Admin)', () => {
  test('can open customers list', async ({ page }) => {
    await page.goto('/customers')
    await expect(page.getByRole('heading', { name: /customers/i })).toBeVisible({ timeout: 15_000 })
  })

  test('can open products list', async ({ page }) => {
    await page.goto('/products')
    await expect(page.getByRole('heading', { name: /products/i })).toBeVisible({ timeout: 15_000 })
  })

  test('can open quotations list', async ({ page }) => {
    await page.goto('/quotations')
    await expect(page.getByRole('heading', { name: /quotations/i })).toBeVisible({ timeout: 15_000 })
  })

  test('can open users list', async ({ page }) => {
    await page.goto('/users')
    await expect(page.getByRole('heading', { name: /users/i })).toBeVisible({ timeout: 15_000 })
  })
})
