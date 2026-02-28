import { readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'

const [, , inputPath, outputPath] = process.argv

if (!inputPath || !outputPath) {
  console.error('Usage: node scripts/render_deliverable_pdf.mjs <input-html-path> <output-pdf-path>')
  process.exit(1)
}

const require = createRequire(import.meta.url)

function loadPlaywright() {
  const resolvePaths = [
    process.cwd(),
    `${process.cwd()}/frontend`,
  ]

  try {
    return require(require.resolve('playwright', { paths: resolvePaths }))
  } catch (error) {
    console.error('Playwright is not installed. Install it in the repo root or frontend workspace before exporting PDFs.')
    throw error
  }
}

const { chromium } = loadPlaywright()
const browser = await chromium.launch({
  executablePath: process.env.DELIVERABLE_PDF_BROWSER_PATH || undefined,
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
