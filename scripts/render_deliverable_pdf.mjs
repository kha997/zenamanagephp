import { constants as fsConstants } from 'node:fs'
import { access, readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { resolve } from 'node:path'
import process from 'node:process'

const require = createRequire(import.meta.url)
const args = process.argv.slice(2)

function printUsage() {
  console.log('Usage: node scripts/render_deliverable_pdf.mjs <input-html-path> <output-pdf-path>')
  console.log('       [--preset a4_clean] [--orientation portrait|landscape]')
  console.log('       [--header-footer true|false] [--margins top,right,bottom,left]')
  console.log('       node scripts/render_deliverable_pdf.mjs --check-deps')
}

function parseCliArguments(argv) {
  if (argv.length < 2) {
    return null
  }

  const [inputPath, outputPath, ...rest] = argv
  const options = {
    inputPath,
    outputPath,
    preset: 'a4_clean',
    orientation: 'portrait',
    headerFooter: true,
    margins: {
      top: '18mm',
      right: '14mm',
      bottom: '18mm',
      left: '14mm',
    },
    projectName: '',
    templateSemver: '',
    generatedAt: '',
  }

  for (let index = 0; index < rest.length; index += 1) {
    const flag = rest[index]
    const value = rest[index + 1]

    if (!flag.startsWith('--') || value === undefined) {
      throw new Error(`Invalid CLI arguments near ${flag ?? '(end)'}.`)
    }

    switch (flag) {
      case '--preset':
        options.preset = value
        break
      case '--orientation':
        options.orientation = value
        break
      case '--header-footer':
        options.headerFooter = value === 'true'
        break
      case '--margins': {
        const [top, right, bottom, left] = value.split(',')
        if (![top, right, bottom, left].every(Boolean)) {
          throw new Error('Margins must be provided as top,right,bottom,left.')
        }
        options.margins = {
          top: `${top}mm`,
          right: `${right}mm`,
          bottom: `${bottom}mm`,
          left: `${left}mm`,
        }
        break
      }
      case '--project-name':
        options.projectName = value
        break
      case '--template-semver':
        options.templateSemver = value
        break
      case '--generated-at':
        options.generatedAt = value
        break
      default:
        throw new Error(`Unsupported flag: ${flag}`)
    }

    index += 1
  }

  return options
}

async function loadPresetCss(preset) {
  const presetPath = resolve(process.cwd(), 'resources', 'pdf', 'presets', `${preset}.css`)
  return readFile(presetPath, 'utf8')
}

function injectCssIntoHtml(html, css) {
  const styleTag = `<style data-deliverable-pdf-preset="a4_clean">\n${css}\n</style>`

  if (/<head\b[^>]*>/i.test(html)) {
    return html.replace(/<head\b[^>]*>/i, (match) => `${match}\n${styleTag}`)
  }

  return `<!DOCTYPE html><html><head>${styleTag}</head><body>${html}</body></html>`
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function formatGeneratedAt(value) {
  if (!value) {
    return ''
  }

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed)
}

function buildHeaderTemplate({ projectName, templateSemver, generatedAt }) {
  const pieces = []

  if (projectName) {
    pieces.push(escapeHtml(projectName))
  }

  if (templateSemver) {
    pieces.push(`v${escapeHtml(templateSemver)}`)
  }

  const timestamp = formatGeneratedAt(generatedAt)
  if (timestamp) {
    pieces.push(escapeHtml(timestamp))
  }

  return `
    <div style="width:100%;font-size:8px;color:#5f6b76;padding:0 10mm;display:flex;justify-content:space-between;align-items:center;">
      <span>${pieces[0] ?? ''}</span>
      <span>${pieces.slice(1).join(' | ')}</span>
    </div>
  `
}

function buildFooterTemplate() {
  return `
    <div style="width:100%;font-size:8px;color:#5f6b76;padding:0 10mm;display:flex;justify-content:center;">
      <span>Page <span class="pageNumber"></span> / <span class="totalPages"></span></span>
    </div>
  `
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

const parsedArgs = parseCliArguments(args)

if (!parsedArgs) {
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
  const html = await readFile(parsedArgs.inputPath, 'utf8')
  const presetCss = await loadPresetCss(parsedArgs.preset)
  const htmlWithPreset = injectCssIntoHtml(html, presetCss)
  const page = await browser.newPage()
  await page.emulateMediaType('print')
  await page.setContent(htmlWithPreset, { waitUntil: 'networkidle' })
  await page.pdf({
    path: parsedArgs.outputPath,
    format: 'A4',
    landscape: parsedArgs.orientation === 'landscape',
    displayHeaderFooter: parsedArgs.headerFooter,
    margin: parsedArgs.margins,
    headerTemplate: parsedArgs.headerFooter ? buildHeaderTemplate(parsedArgs) : '<div></div>',
    footerTemplate: parsedArgs.headerFooter ? buildFooterTemplate() : '<div></div>',
    printBackground: true,
    preferCSSPageSize: true,
  })
  await page.close()
} finally {
  await browser.close()
}
