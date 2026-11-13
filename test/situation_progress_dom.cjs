/* DOM simulation using rows rendered from the unmodified native PHP card source. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const script = fs.readFileSync(require('node:path').join(__dirname, '../js/situation_progress.js'), 'utf8');
let checks = 0;
function run(rows, config, expected, twice = false, loading = false) {
	const clone = structuredClone(rows);
	function cell(data = {}) {
		return Object.assign({textContent: '', colSpan: 1, rowSpan: 1, links: [], className: '', innerHTML: ''}, data, {
			querySelectorAll() { return this.links.map(href => ({href})); }
		});
	}
	const table = {dataset: {}, rows: clone.map(row => ({
		classList: {contains: name => row.className.split(' ').includes(name)},
		cells: row.cells.map(cell),
		insertCell(index) { const td = cell(); this.cells.splice(index, 0, td); return td; },
		insertBefore(td, anchor) { this.cells.splice(this.cells.indexOf(anchor), 0, td); return td; }
	}))};
	const before = JSON.stringify(table);
	let listener;
	const context = {window: {}, URL, location: {origin: 'https://fixture.test'}, document: {
		baseURI: 'https://fixture.test/erp/compta/facture/card.php?facid=2',
		readyState: loading ? 'loading' : 'complete',
		querySelectorAll: () => [table], createElement: () => cell(),
		addEventListener: (event, callback) => { listener = callback; }
	}};
	vm.runInNewContext(script, context);
	context.window.jpsunSituationProgress(config);
	if (loading) { assert.equal(JSON.stringify(table), before); listener(); }
	if (expected) {
		assert.equal(table.dataset.jpsunProgress, '1');
		for (const row of table.rows) assert.equal(row.cells.reduce((sum, td) => sum + td.colSpan, 0), 7);
		assert.equal(table.rows[0].cells[3].innerHTML, config.header);
		assert.equal(table.rows[1].cells[3].textContent, config.values[1]);
		assert.equal(table.rows[2].cells[3].textContent, config.values[2]);
		assert.equal(table.rows[4].cells[3].textContent, config.values[3]);
		// Existing totals, references, status and amount cells remain the same objects/content.
		for (let i = 0; i < rows.length; i++) {
			const old = rows[i].cells;
			const after = table.rows[i].cells;
			assert.equal(after[after.length - 2].textContent, old[old.length - 2].textContent);
		}
		if (twice) { const once = JSON.stringify(table); context.window.jpsunSituationProgress(config); assert.equal(JSON.stringify(table), once); }
	} else assert.equal(JSON.stringify(table), before, 'Unexpected structure must remain entirely unchanged');
	checks++;
}
for (const rows of fixture.tables) {
	const config = fixture.config;
	run(rows, config, true, true);
	run(rows, config, true, false, true);
	let altered = structuredClone(rows); altered[1].cells[0].links = ['/erp/compta/facture/card.php?facid=999']; run(altered, config, false);
	altered = structuredClone(rows); altered[4].cells[0].links = ['/erp/compta/facture/card.php?facid=1']; run(altered, config, false);
	altered = structuredClone(rows); altered[1].cells[0].links = ['https://elsewhere.test/erp/compta/facture/card.php?facid=1']; run(altered, config, false);
	altered = structuredClone(rows); altered[0].cells[2].textContent = 'Other column'; run(altered, config, false);
	altered = structuredClone(rows); altered[4].cells.pop(); run(altered, config, false);
	altered = structuredClone(rows); altered[3].cells[0].colSpan = 1; run(altered, config, false);
	altered = structuredClone(rows); altered[2].cells[0].rowSpan = 2; run(altered, config, false);
	const missing = structuredClone(config); delete missing.values[3]; run(rows, missing, false);
	const extra = structuredClone(config); extra.values[4] = '20 %'; run(rows, extra, false);
	if (rows[0].cells.length === 7) {
		altered = structuredClone(rows); altered[0].cells[3].textContent = 'Progress'; run(altered, config, false);
		altered = structuredClone(rows); altered[4].cells[3].textContent = 'Already used'; run(altered, config, false);
	}
}
console.log(`OK ${checks} DOM scenarios (native table rows; simulated DOM, no browser acceptance)`);
