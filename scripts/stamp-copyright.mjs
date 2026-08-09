/**
 * Build-Schritt: schreibt einen Copyright-Vermerk in die Metadaten aller
 * Bilder im fertigen dist/-Ordner.
 *
 * WARUM NICHT MIT EINER BILDBIBLIOTHEK
 * Der naheliegende Weg wäre sharp: Bild einlesen, Metadaten setzen, neu
 * schreiben. Das rechnet das Bild aber neu durch — bei JPEG und WebP heißt
 * das Qualitätsverlust und eine unvorhersehbare neue Dateigröße. Genau das
 * darf hier nicht passieren: Die Seite lebt von schnellen Ladezeiten, und
 * ein Bildschutz, der Core Web Vitals verschlechtert, wäre ein schlechtes
 * Geschäft.
 *
 * Deshalb wird stattdessen direkt am Dateiformat gearbeitet. Die Bildpunkte
 * werden dabei nicht angefasst — es kommt nur ein zusätzlicher Metadaten-
 * Block hinzu:
 *
 *   JPEG  ein APP1-Segment mit EXIF-Block (Copyright, Artist, Description),
 *         eingefügt direkt hinter dem Dateikopf.
 *   PNG   tEXt-Chunks (Copyright, Author, Description) hinter dem IHDR.
 *   SVG   ein XML-Kommentar plus <metadata> mit dc:rights.
 *   WEBP  wird übersprungen — dort ließe sich EXIF nur durch Umbau des
 *         RIFF-Containers auf das erweiterte VP8X-Format einfügen. Das
 *         Risiko, dabei eine Datei zu beschädigen, steht in keinem
 *         Verhältnis zum Nutzen; der Vermerk steht für dasselbe Motiv
 *         bereits in der JPEG-Fassung.
 *
 * Der Zugewinn ist nüchtern zu betrachten: Metadaten belegen die Urheber-
 * schaft, wenn ein Bild irgendwo auftaucht, und ihr Entfernen ist nach
 * § 95c UrhG unzulässig. Sie verhindern das Kopieren nicht.
 *
 * Der Schritt läuft NUR über dist/. Die Originale unter public/ bleiben
 * unverändert, damit ein zweiter Build nie auf einer bereits gestempelten
 * Datei aufsetzt.
 */

import { readdirSync, statSync, readFileSync, writeFileSync } from 'node:fs'
import { join, extname } from 'node:path'

const HOLDER = 'Leandro Jung / JunglineLocal'
const YEAR = new Date().getFullYear()
const COPYRIGHT = `© ${YEAR} ${HOLDER}. Alle Rechte vorbehalten.`
const DESCRIPTION = 'Nutzung nur mit schriftlicher Genehmigung. Text und Data Mining vorbehalten (§ 44b Abs. 3 UrhG) — https://jungline.de/nutzungsbedingungen/'

// ---------------------------------------------------------------------------
// CRC32 (für PNG-Chunks). Bewusst selbst implementiert statt über zlib.crc32:
// die Funktion gibt es erst ab Node 20.15, und der Build soll nicht an einer
// Patch-Version des Runners scheitern.
// ---------------------------------------------------------------------------
const CRC_TABLE = (() => {
  const table = new Int32Array(256)
  for (let n = 0; n < 256; n++) {
    let c = n
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    table[n] = c
  }
  return table
})()

function crc32(buf) {
  let c = 0xffffffff
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8)
  return (c ^ 0xffffffff) >>> 0
}

// ---------------------------------------------------------------------------
// JPEG
// ---------------------------------------------------------------------------

/**
 * Baut einen minimalen EXIF-Block (TIFF, little endian) mit drei
 * ASCII-Feldern. Aufbau nach TIFF 6.0:
 *
 *   Header    8 Bytes: "II", Version 42, Offset der ersten IFD (= 8)
 *   IFD0      2 Bytes Anzahl + 12 Bytes je Eintrag + 4 Bytes "nächste IFD"
 *   Datenteil alle Zeichenketten, die länger als 4 Bytes sind
 *
 * Einträge müssen nach Tag-Nummer aufsteigend sortiert sein — daran halten
 * sich manche Leseprogramme strenger als andere.
 */
function buildExif(fields) {
  const entries = fields
    .map(([tag, text]) => ({ tag, bytes: Buffer.from(text + '\0', 'utf8') }))
    .sort((a, b) => a.tag - b.tag)

  const HEADER = 8
  const ifdSize = 2 + entries.length * 12 + 4
  const dataStart = HEADER + ifdSize

  const dataChunks = []
  let dataOffset = dataStart

  const ifd = Buffer.alloc(ifdSize)
  ifd.writeUInt16LE(entries.length, 0)

  entries.forEach((entry, i) => {
    const at = 2 + i * 12
    ifd.writeUInt16LE(entry.tag, at)          // Tag
    ifd.writeUInt16LE(2, at + 2)              // Typ 2 = ASCII
    ifd.writeUInt32LE(entry.bytes.length, at + 4)

    if (entry.bytes.length <= 4) {
      // Kurze Werte stehen direkt im Eintrag statt im Datenteil.
      entry.bytes.copy(ifd, at + 8)
    } else {
      ifd.writeUInt32LE(dataOffset, at + 8)
      dataChunks.push(entry.bytes)
      dataOffset += entry.bytes.length
      // TIFF verlangt gerade Offsets.
      if (entry.bytes.length % 2 === 1) {
        dataChunks.push(Buffer.from([0]))
        dataOffset += 1
      }
    }
  })
  ifd.writeUInt32LE(0, 2 + entries.length * 12) // keine weitere IFD

  const header = Buffer.alloc(HEADER)
  header.write('II', 0, 'ascii')
  header.writeUInt16LE(42, 2)
  header.writeUInt32LE(HEADER, 4)

  return Buffer.concat([header, ifd, ...dataChunks])
}

function stampJpeg(buf) {
  if (buf.length < 4 || buf[0] !== 0xff || buf[1] !== 0xd8) return null // kein SOI

  // Vorhandene Segmente durchgehen: Liegt schon ein EXIF-Block vor, wird
  // nichts angefasst — doppelte APP1-Segmente sind ungültig.
  let pos = 2
  let insertAt = 2
  while (pos + 4 <= buf.length && buf[pos] === 0xff) {
    const marker = buf[pos + 1]
    if (marker === 0xd8 || marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7)) { pos += 2; continue }
    if (marker === 0xda) break // Start of Scan — ab hier kommen Bilddaten
    const len = buf.readUInt16BE(pos + 2)
    if (len < 2) return null
    if (marker === 0xe1 && buf.slice(pos + 4, pos + 10).toString('ascii') === 'Exif\0\0') return null
    // Nach einem JFIF-APP0 einfügen, so will es die übliche Reihenfolge.
    if (marker === 0xe0) insertAt = pos + 2 + len
    pos += 2 + len
  }

  const exif = buildExif([
    [0x010e, DESCRIPTION],  // ImageDescription
    [0x013b, HOLDER],       // Artist
    [0x8298, COPYRIGHT],    // Copyright
  ])
  const payload = Buffer.concat([Buffer.from('Exif\0\0', 'ascii'), exif])
  if (payload.length + 2 > 0xffff) return null

  const segment = Buffer.alloc(4)
  segment[0] = 0xff
  segment[1] = 0xe1
  segment.writeUInt16BE(payload.length + 2, 2)

  return Buffer.concat([buf.slice(0, insertAt), segment, payload, buf.slice(insertAt)])
}

// ---------------------------------------------------------------------------
// PNG
// ---------------------------------------------------------------------------

const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])

function pngTextChunk(keyword, text) {
  // tEXt ist Latin-1. "©" liegt dort auf 0xA9 und überlebt die Umwandlung;
  // alles außerhalb wird von Buffer.from(..., 'latin1') ersetzt, was für
  // diese festen Texte nicht vorkommt.
  const data = Buffer.concat([
    Buffer.from(keyword, 'latin1'),
    Buffer.from([0]),
    Buffer.from(text, 'latin1'),
  ])
  const type = Buffer.from('tEXt', 'ascii')
  const length = Buffer.alloc(4)
  length.writeUInt32BE(data.length, 0)
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(Buffer.concat([type, data])), 0)
  return Buffer.concat([length, type, data, crc])
}

function stampPng(buf) {
  if (buf.length < 8 || !buf.slice(0, 8).equals(PNG_SIGNATURE)) return null
  if (buf.includes(Buffer.from('JunglineLocal', 'latin1'))) return null // schon gestempelt

  // Erster Chunk muss IHDR sein; direkt dahinter wird eingefügt.
  const ihdrLength = buf.readUInt32BE(8)
  if (buf.slice(12, 16).toString('ascii') !== 'IHDR') return null
  const insertAt = 8 + 4 + 4 + ihdrLength + 4

  const chunks = Buffer.concat([
    pngTextChunk('Copyright', COPYRIGHT),
    pngTextChunk('Author', HOLDER),
    pngTextChunk('Description', DESCRIPTION),
  ])

  return Buffer.concat([buf.slice(0, insertAt), chunks, buf.slice(insertAt)])
}

// ---------------------------------------------------------------------------
// SVG
// ---------------------------------------------------------------------------

function stampSvg(text) {
  if (text.includes('JunglineLocal')) return null

  const open = text.match(/<svg\b[^>]*>/)
  if (!open) return null

  const metadata = `<metadata><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/"><rdf:Description><dc:rights>${COPYRIGHT}</dc:rights><dc:creator>${HOLDER}</dc:creator></rdf:Description></rdf:RDF></metadata>`
  const at = open.index + open[0].length

  return `<!-- ${COPYRIGHT} ${DESCRIPTION} -->\n` + text.slice(0, at) + metadata + text.slice(at)
}

// ---------------------------------------------------------------------------
// Durchlauf über dist/
// ---------------------------------------------------------------------------

function walk(dir, out = []) {
  for (const entry of readdirSync(dir)) {
    const abs = join(dir, entry)
    if (statSync(abs).isDirectory()) walk(abs, out)
    else out.push(abs)
  }
  return out
}

export function stampCopyright(distDir) {
  const report = { stamped: 0, skipped: 0, grew: 0, failed: 0 }

  for (const file of walk(distDir)) {
    const ext = extname(file).toLowerCase()
    if (!['.jpg', '.jpeg', '.png', '.svg'].includes(ext)) continue

    try {
      if (ext === '.svg') {
        const text = readFileSync(file, 'utf8')
        const next = stampSvg(text)
        if (next === null) { report.skipped++; continue }
        writeFileSync(file, next, 'utf8')
        report.stamped++
        continue
      }

      const buf = readFileSync(file)
      const next = ext === '.png' ? stampPng(buf) : stampJpeg(buf)
      if (next === null) { report.skipped++; continue }

      // Sicherheitsnetz: Der Vermerk darf ein Bild nur um seine eigene
      // Größe wachsen lassen (wenige hundert Bytes). Alles darüber wäre ein
      // Hinweis darauf, dass beim Zusammensetzen etwas schiefgegangen ist —
      // dann bleibt lieber das Original stehen.
      if (next.length > buf.length + 2048) { report.grew++; continue }

      writeFileSync(file, next)
      report.stamped++
    } catch (err) {
      report.failed++
      console.warn(`[copyright] ${file}: ${err.message}`)
    }
  }

  return report
}

/**
 * Vite-Plugin-Hülle. closeBundle läuft, nachdem dist/ vollständig
 * geschrieben wurde — inklusive der aus public/ kopierten Dateien.
 *
 * Ein Fehler hier darf den Build nie zum Scheitern bringen: Ein Bild ohne
 * Copyright-Vermerk ist ein kleiner Mangel, eine Seite, die nicht deployt
 * werden kann, ein großer.
 */
export function copyrightMetadata() {
  return {
    name: 'jungline-copyright-metadata',
    apply: 'build',
    closeBundle() {
      try {
        const report = stampCopyright('dist')
        console.log(
          `[copyright] ${report.stamped} Bilder gestempelt, ${report.skipped} übersprungen` +
          (report.grew ? `, ${report.grew} verworfen (zu groß)` : '') +
          (report.failed ? `, ${report.failed} fehlgeschlagen` : ''),
        )
      } catch (err) {
        console.warn(`[copyright] Schritt übersprungen: ${err.message}`)
      }
    },
  }
}
