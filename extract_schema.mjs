import fs from 'fs';
import readline from 'readline';

const FILE = 'C:\\xampp\\htdocs\\hatdog\\staging_extract.sql';
const MAX_LINES = 200000;

const HEADER_COLS_SHOW = [
  'UserPK_TransH', 'AccountName_TransH', 'DateIssue_TransH', 'RecordAddedDate_TransH',
  'Module_TransH', 'ModuleSubType_TransH', 'SalesPurchType_SalePurch',
  'InChargedBy_TransH', 'TotalAmount_TransH', 'SalesGrossLocalAmount_SalePurch',
  'SalesNetLocalAmount_SalePurch', 'DiscountAmount_SalePurch', 'SalesDiscount_SalePurch',
  'Status_TransH', 'Particulars_TransH', 'PreparedBy_TransH', 'CheckedBy_TransH',
  'PackedBy_TransH', 'Is_SalePurch', 'Remarks_TransH'
];

const LEDGER_COLS_SHOW = [
  'SysFK_TransH_LdgrEntries', 'SysFK_Invty_LdgrEntries',
  'DRAmount_LdgrEntries', 'DRBaseUnitQty_LdgrEntries',
  'Particulars_LdgrEntries', 'Description_LdgrEntries'
];

const TARGET_TABLES = ['transaction_header', 'transaction_ledger_entries'];

function parseRowValues(valStr) {
  const vals = [];
  let inQuote = false, escaped = false, current = '';
  for (let i = 0; i < valStr.length; i++) {
    const ch = valStr[i];
    if (escaped) { current += ch; escaped = false; continue; }
    if (ch === '\\' && inQuote) { current += ch; escaped = true; continue; }
    if (ch === "'") { inQuote = !inQuote; current += ch; continue; }
    if (!inQuote && ch === ',') { vals.push(current.trim()); current = ''; continue; }
    current += ch;
  }
  vals.push(current.trim());
  return vals;
}

function splitRowTuples(str) {
  const tuples = [];
  let depth = 0, inQuote = false, escaped = false, current = '';
  for (let i = 0; i < str.length; i++) {
    const ch = str[i];
    if (escaped) { current += ch; escaped = false; continue; }
    if (ch === '\\' && inQuote) { current += ch; escaped = true; continue; }
    if (ch === "'") { inQuote = !inQuote; current += ch; continue; }
    if (!inQuote) {
      if (ch === '(') { depth++; if (depth === 1) { current = ''; continue; } }
      if (ch === ')') { depth--; if (depth === 0) { tuples.push(current); current = ''; continue; } }
    }
    current += ch;
  }
  if (current.trim()) tuples.push(current.trim());
  return tuples;
}

function stripQuotes(s) {
  if (s === undefined || s === null) return '';
  const t = s.trim();
  if (t.startsWith("'") && t.endsWith("'") && t.length >= 2) return t.slice(1, -1);
  if (t === 'NULL' || t === 'null') return null;
  return t;
}

function processInsertBuf(buf) {
  const { table, columns, buf: raw } = buf;
  const colsShow = table === 'transaction_header' ? HEADER_COLS_SHOW : LEDGER_COLS_SHOW;

  // Find the balancing close
  let depth = 0, inQuote = false, escaped = false, lastCloseParen = -1;
  for (let i = 0; i < raw.length; i++) {
    const ch = raw[i];
    if (escaped) { escaped = false; continue; }
    if (ch === '\\' && inQuote) { escaped = true; continue; }
    if (ch === "'") { inQuote = !inQuote; continue; }
    if (!inQuote) {
      if (ch === '(') depth++;
      if (ch === ')') { depth--; if (depth === 0) lastCloseParen = i; }
    }
  }
  if (depth !== 0 || lastCloseParen === -1) return null;

  const endIdx = raw.lastIndexOf(');');
  const fullStr = endIdx !== -1 ? raw.substring(0, endIdx) : raw;
  const tuples = splitRowTuples(fullStr);
  if (tuples.length === 0) return null;

  const rows = [];
  for (const rowStr of tuples) {
    const rowVals = parseRowValues(rowStr);
    const rowMap = {};
    for (let ci = 0; ci < columns.length && ci < rowVals.length; ci++) {
      rowMap[columns[ci]] = rowVals[ci];
    }
    rows.push(rowMap);
  }
  return rows;
}

let lineCount = 0;
let schemas = {};
let inCreate = false;
let createTable = null;
let createLines = [];

let headerSalesFound = 0;
let ledgerFound = 0;

const rl = readline.createInterface({
  input: fs.createReadStream(FILE, { encoding: 'utf8', highWaterMark: 1024 * 1024 }),
  crlfDelay: Infinity,
});

for await (const line of rl) {
  lineCount++;
  if (lineCount > MAX_LINES) break;
  if (lineCount % 50000 === 0) process.stderr.write(`line ${lineCount}...\n`);

  const trimmed = line.trim();

  // CREATE TABLE
  if (!inCreate) {
    const cm = trimmed.match(/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\($/i);
    if (cm && TARGET_TABLES.includes(cm[1])) {
      inCreate = true;
      createTable = cm[1];
      createLines = [];
      continue;
    }
  } else {
    if (/^\)\s*(ENGINE|;)/i.test(trimmed) || trimmed === ')') {
      const cols = [];
      for (const cl of createLines) {
        const tl = cl.trim();
        const m = tl.match(/^`([^`]+)`/);
        if (m) cols.push(m[1]);
      }
      schemas[createTable] = cols;
      inCreate = false;
      continue;
    }
    createLines.push(line);
    continue;
  }

  // Detect INSERT INTO for target tables (single-line statements)
  const im = trimmed.match(
    /^(?:INSERT|insert)\s+INTO\s+`?(transaction_header|transaction_ledger_entries)`?\s*\(([^)]*)\)\s*VALUES\s*/i
  );
  if (im) {
    const tname = im[1];
    const cols = im[2].split(',').map(c => c.trim().replace(/`/g, ''));
    const afterVal = trimmed.substring(im[0].length);
    const insertBuf = { table: tname, columns: cols, buf: afterVal };
    const rows = processInsertBuf(insertBuf);
    if (rows) {
      for (const rowMap of rows) {
        if (tname === 'transaction_header') {
          const mod = stripQuotes(rowMap['Module_TransH']);
          if (mod === 'SALES' && headerSalesFound < 3) {
            headerSalesFound++;
            const out = { table: tname, row: headerSalesFound, columns: {} };
            for (const c of HEADER_COLS_SHOW) {
              out.columns[c] = stripQuotes(rowMap[c]);
            }
            process.stdout.write(JSON.stringify(out) + '\n');
          }
        } else if (tname === 'transaction_ledger_entries' && ledgerFound < 3) {
          ledgerFound++;
          const out = { table: tname, row: ledgerFound, columns: {} };
          for (const c of LEDGER_COLS_SHOW) {
            out.columns[c] = stripQuotes(rowMap[c]);
          }
          process.stdout.write(JSON.stringify(out) + '\n');
        }
      }
      if (headerSalesFound >= 3 && ledgerFound >= 3) {
        process.stdout.write(JSON.stringify({ type: 'schemas', data: schemas }) + '\n');
        process.exit(0);
      }
    }
    continue;
  }
}

process.stdout.write(JSON.stringify({ type: 'schemas', data: schemas }) + '\n');
process.stderr.write(`headerSalesFound: ${headerSalesFound}, ledgerFound: ${ledgerFound}\n`);
