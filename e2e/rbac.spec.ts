import { expect, test } from '@playwright/test'
import { SALES, waitForLoginForm } from './helpers/auth'

test.describe('Sales staff access', () => {
  test.use({ storageState: { cookies: [], origins: [] } })

  test('cannot access roles management', async ({ page }) => {
    await waitForLoginForm(page)
    await page.locator('#email').fill(SALES.email)
    await page.locator('#password').fill(SALES.password)
    await page.getByRole('button', { name: 'Sign In' }).click()

    const stillOnLogin = await page.waitForURL(/\/login/, { timeout: 5_000 }).then(() => true).catch(() => false)
    test.skip(stillOnLogin, 'Sales user not seeded — run: php artisan db:seed --class=Database\\Seeders\\UserSeeder')

    await expect(page).toHaveURL(/\/dashboard/, { timeout: 30_000 })
    await page.goto('/masters/roles')
    await expect(page).toHaveURL(/\/403/, { timeout: 15_000 })
  })
})

test.describe('Super admin access', () => {
  test('can access roles management', async ({ page }) => {
    await page.goto('/masters/roles')
    await expect(page.getByRole('heading', { name: /roles & permissions/i })).toBeVisible({ timeout: 15_000 })
    await expect(page).not.toHaveURL(/\/403/)
  })
})
