import { expect, type Page } from '@playwright/test'

export const ADMIN = {
  email: process.env.E2E_ADMIN_EMAIL ?? 'admin@shreeaxar.com',
  password: process.env.E2E_ADMIN_PASSWORD ?? 'admin@private',
}

export const SALES = {
  email: process.env.E2E_SALES_EMAIL ?? 'sales@shreeaxar.com',
  password: process.env.E2E_SALES_PASSWORD ?? 'sales@private',
}

export async function waitForLoginForm(page: Page): Promise<void> {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('#email')).toBeVisible({ timeout: 30_000 })
  await expect(page.locator('#password')).toBeVisible()
}

export async function login(page: Page, email: string, password: string): Promise<void> {
  await waitForLoginForm(page)
  await page.locator('#email').fill(email)
  await page.locator('#password').fill(password)
  await page.getByRole('button', { name: 'Sign In' }).click()
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 30_000 })
}
