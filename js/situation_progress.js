/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
(function () {
	"use strict";
	window.jpsunSituationProgress = function (config) {
		function enhance() {
			const tables = document.querySelectorAll("table.situationstable");
			if (tables.length !== 1) return;
			const table = tables[0];
			if (table.dataset.jpsunProgress) return;
			const rows = Array.from(table.rows);
			if (rows.length < 3) return;
			const header = rows[0];
			const width = header.cells.length;
			if (width !== 6 && width !== 7) return;
			const text = cell => cell.textContent.replace(/\s+/g, " ").trim();
			if (!header.classList.contains("liste_titre")
				|| text(header.cells[2]) !== config.situation
				|| text(header.cells[width - 3]) !== config.amountHT
				|| text(header.cells[width - 2]) !== config.amountTTC
				|| (width === 7 && text(header.cells[3]) !== "")) return;

			// Validate every row before changing any of them. Unknown structures stay native.
			const plan = [];
			const seen = new Set();
			for (const row of rows.slice(1)) {
				const cells = Array.from(row.cells);
				if (cells.some(cell => cell.rowSpan !== 1)
					|| cells.reduce((sum, cell) => sum + cell.colSpan, 0) !== width) return;
				let anchor;
				let value = "";
				if (cells.every(cell => cell.colSpan === 1)) {
					const links = cells[0].querySelectorAll("a[href]");
					if (links.length !== 1) return;
					const url = new URL(links[0].href, document.baseURI);
					const id = url.searchParams.get("facid") || url.searchParams.get("id");
					if (url.origin !== location.origin || url.pathname !== config.path
						|| !Object.prototype.hasOwnProperty.call(config.values, id) || seen.has(id)) return;
					seen.add(id);
					value = config.values[id];
					anchor = cells[3];
				} else if (cells[0].colSpan === 2 && cells.slice(1).every(cell => cell.colSpan === 1)) {
					anchor = cells[2];
				} else if (cells[0].colSpan === 3 && cells.slice(1).every(cell => cell.colSpan === 1)) {
					anchor = cells[1];
				} else return;
				if (width === 7 && text(anchor) !== "") return;
				plan.push({row, anchor, value});
			}
			if (seen.size !== Object.keys(config.values).length) return;
			const title = width === 7 ? header.cells[3] : header.insertCell(3);
			title.className = "right";
			// Trusted HTML from the native Form::textwithpicto helper, never invoice data.
			title.innerHTML = config.header;
			for (const item of plan) {
				const cell = width === 7 ? item.anchor : item.row.insertBefore(document.createElement("td"), item.anchor);
				cell.className = "right";
				cell.textContent = item.value;
			}
			table.dataset.jpsunProgress = "1";
		}
		if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", enhance, {once: true});
		else enhance();
	};
}());
