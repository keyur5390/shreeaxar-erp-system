import { expect, test } from '@playwright/test'
import { ADMIN, login, waitForLoginForm } from './helpers/auth'

test.describe('Authentication', () => {
  test('shows login page', async ({ page }) => {
    await waitForLoginForm(page)
    await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible()
  })

  test('rejects invalid credentials', async ({ page }) => {
    await waitForLoginForm(page)
    await page.locator('#email').fill('admin@shreeaxar.com')
    await page.locator('#password').fill('wrong-password')
    await page.getByRole('button', { name: 'Sign In' }).click()

    await expect(page.getByText(/invalid email or password/i)).toBeVisible({ timeout: 15_000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('admin can sign in and reach dashboard', async ({ page }) => {
    await login(page, ADMIN.email, ADMIN.password)
    await expect(page.getByRole('heading', { name: /dashboard/i })).toBeVisible()
  })

  test('redirects unauthenticated users to login', async ({ page }) => {
    await page.goto('/customers')
    await expect(page).toHaveURL(/\/login/, { timeout: 15_000 })
  })
})
