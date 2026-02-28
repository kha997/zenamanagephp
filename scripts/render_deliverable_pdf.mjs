import { constants as fsConstants } from 'node:fs'
import { access, readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import process from 'node:process'

const require = createRequire(import.meta.url)
const args = process.argv.slice(2)

function printUsage() {
  console.log('Usage: node scripts/render_deliverable_pdf.mjs <input-html-path> <output-pdf-path>')
  console.log('       node scripts/render_deliverable_pdf.mjs --check-deps')
}

function loadPlaywright() {
  try {
    return require(require.resolve('playwright', { paths: [process.cwd()] }))
  } catch (error) {
    console.error('Playwright is not installed in the repo root.')
    throw error
  }
}

async function resolveExecutablePath(chromium) {
  const configuredPath = process.env.DELIVERABLE_PDF_BROWSER_PATH

  if (configuredPath) {
    await access(configuredPath, fsConstants.X_OK)
    return configuredPath
  }

  const executablePath = chromium.executablePath()

  if (!executablePath) {
    throw new Error('Chromium executable path was not resolved.')
  }

  await access(executablePath, fsConstants.X_OK)
  return executablePath
}

async function assertDependenciesAvailable() {
  try {
    const { chromium } = loadPlaywright()
    await resolveExecutablePath(chromium)
    return true
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error))
    return false
  }
}

if (args.includes('--help') || args.includes('-h')) {
  printUsage()
  process.exit(0)
}

if (args.includes('--check-deps')) {
  process.exit((await assertDependenciesAvailable()) ? 0 : 1)
}

const [inputPath, outputPath] = args

if (!inputPath || !outputPath) {
  printUsage()
  process.exit(1)
}

const { chromium } = loadPlaywright()
const executablePath = await resolveExecutablePath(chromium)
const browser = await chromium.launch({
  executablePath,
  headless: true,
})

try {
  const html = await readFile(inputPath, 'utf8')
  const page = await browser.newPage()
  await page.setContent(html, { waitUntil: 'networkidle' })
  await page.pdf({
    path: outputPath,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
  })
  await page.close()
} finally {
  await browser.close()
}
