import { chromium, type FullConfig } from '@playwright/test'
import { ADMIN } from './helpers/auth'

async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = config.projects[0]?.use?.baseURL ?? 'http://127.0.0.1:8000'
  const browser = await chromium.launch()
  const page = await browser.newPage({ baseURL })

  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.locator('#email').waitFor({ state: 'visible', timeout: 30_000 })
  await page.locator('#email').fill(ADMIN.email)
  await page.locator('#password').fill(ADMIN.password)
  await page.getByRole('button', { name: 'Sign In' }).click()
  await page.waitForURL(/\/dashboard/, { timeout: 30_000 })

  await page.context().storageState({ path: 'e2e/.auth/admin.json' })
  await browser.close()
}

export default globalSetup
