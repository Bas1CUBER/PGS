(function(){
            var labels = PGS.page.yearLabels;
            var avg = PGS.page.avgSeries;
            var special = PGS.page.selectedSeries;
            var sum = PGS.page.sumSeries;
            var primary = '#196a6b';
            var accent = '#f59e0b';
            var gray = '#64748b';
            new Chart(document.getElementById('chartAvg'), {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Average', data: avg, borderColor: primary, backgroundColor: 'rgba(25,106,107,0.2)', tension: 0.3, fill: true }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
            new Chart(document.getElementById('chartCount'), {
                type: 'pie',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        label: 'Access to All Level of Care', 
                        data: special, 
                        backgroundColor: labels.map(function(_, i){
                            var palette = ['#196a6b','#f59e0b','#64748b','#10b981','#ef4444','#8b5cf6','#22c55e','#0ea5e9','#f97316','#e11d48'];
                            return palette[i % palette.length];
                        })
                    }] 
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } } }
            });
            new Chart(document.getElementById('chartSum'), {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Sum', data: sum, backgroundColor: gray }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        })();
