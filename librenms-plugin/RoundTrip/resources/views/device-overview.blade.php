<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading">
                <strong>{{ $title }}</strong>
                <a href="/roundtrip" target="_blank" class="pull-right" style="font-size: 11px;">[Open RoundTrip]</a>
            </div>
            <div class="panel-body" style="padding: 10px;">
                <div id="roundtrip-container-{{ $device_id }}">
                    <div style="text-align: center; padding: 20px;">
                        <i class="fa fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
(function() {
    var config = {
        deviceId: {{ $device_id }},
        hostname: '{{ addslashes($hostname) }}',
        sysName: '{{ addslashes($sysName ?? '') }}',
        ip: '{{ $ip }}',
        apiUrl: '{{ $api_url }}',
        apiToken: '{{ $api_token }}'
    };
    
    var container = document.getElementById('roundtrip-container-' + config.deviceId);
    var currentTarget = null;
    var currentTimeRange = 60; // minutes
    
    if (!config.apiToken) {
        container.innerHTML = '<div class="alert alert-warning" style="margin-bottom: 0;"><i class="fa fa-exclamation-triangle"></i> RoundTrip API token not configured. Go to <a href="/plugin/settings/RoundTrip">Plugin Settings</a>.</div>';
        return;
    }
    
    // Fetch targets and find matching one
    fetch(config.apiUrl + '/api/targets', {
        headers: {
            'Authorization': 'Bearer ' + config.apiToken,
            'Accept': 'application/json'
        }
    })
    .then(function(response) {
        if (!response.ok) {
            if (response.status === 401) throw new Error('Authentication failed. Check your API token.');
            throw new Error('Failed to connect to RoundTrip API');
        }
        return response.json();
    })
    .then(function(targets) {
        for (var i = 0; i < targets.length; i++) {
            var t = targets[i];
            if (t.host === config.hostname || t.host === config.ip || 
                t.name === config.hostname || t.name === config.sysName) {
                currentTarget = t;
                break;
            }
        }
        
        if (!currentTarget) {
            showAddButton();
        } else {
            loadAndShowChart();
            // Auto-refresh every 5 seconds
            setInterval(loadAndShowChart, 5000);
        }
    })
    .catch(function(err) {
        container.innerHTML = '<div class="alert alert-warning" style="margin-bottom: 0;"><i class="fa fa-exclamation-triangle"></i> ' + err.message + '</div>';
    });
    
    function loadAndShowChart() {
        var from = new Date(Date.now() - currentTimeRange * 60 * 1000).toISOString();
        var to = new Date().toISOString();
        
        fetch(config.apiUrl + '/api/targets/' + currentTarget.id + '/series?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to), {
            headers: {
                'Authorization': 'Bearer ' + config.apiToken,
                'Accept': 'application/json'
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            showChart(currentTarget, data.points || []);
        });
    }
    
    function showAddButton() {
        var name = config.sysName || config.hostname;
        container.innerHTML = '<div style="text-align: center; padding: 15px;">' +
            '<p class="text-muted">No RoundTrip target found for this device.</p>' +
            '<button type="button" class="btn btn-primary btn-sm" id="roundtrip-add-btn-' + config.deviceId + '">' +
            '<i class="fa fa-plus"></i> Add to RoundTrip</button></div>';
        
        document.getElementById('roundtrip-add-btn-' + config.deviceId).onclick = function() {
            this.disabled = true;
            this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
            
            fetch(config.apiUrl + '/api/targets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + config.apiToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: name, host: config.ip, enabled: true, interval_seconds: 5 })
            })
            .then(function(response) {
                if (response.ok) location.reload();
                else return response.json().then(function(data) { throw new Error(data.message || 'Failed to add target'); });
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
                var btn = document.getElementById('roundtrip-add-btn-' + config.deviceId);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plus"></i> Add to RoundTrip';
            });
        };
    }
    
    function showChart(target, points) {
        var validPoints = points.filter(function(p) { return p.avg_ms !== null; });
        
        // Build header with time range selector
        var html = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
            '<div><strong>Target:</strong> ' + target.name + ' (' + target.host + ') ' +
            '<span class="label label-' + (target.enabled ? 'success' : 'default') + '">' + (target.enabled ? 'Active' : 'Disabled') + '</span></div>' +
            '<div class="btn-group btn-group-xs" id="roundtrip-timerange-' + config.deviceId + '">' +
            '<button type="button" class="btn btn-default" data-range="15">15m</button>' +
            '<button type="button" class="btn btn-default" data-range="30">30m</button>' +
            '<button type="button" class="btn btn-default" data-range="60">1h</button>' +
            '<button type="button" class="btn btn-default" data-range="180">3h</button>' +
            '<button type="button" class="btn btn-default" data-range="360">6h</button>' +
            '<button type="button" class="btn btn-default" data-range="720">12h</button>' +
            '<button type="button" class="btn btn-default" data-range="1440">24h</button>' +
            '</div></div>';
        
        html += '<div id="roundtrip-chart-' + config.deviceId + '" style="width: 100%; height: 200px; position: relative;"></div>';
        
        // Stats row
        if (validPoints.length > 0) {
            var avgValues = validPoints.map(function(p) { return p.avg_ms; });
            var minValues = validPoints.map(function(p) { return p.min_ms; }).filter(function(v) { return v !== null; });
            var maxValues = validPoints.map(function(p) { return p.max_ms; }).filter(function(v) { return v !== null; });
            var lossCount = validPoints.filter(function(p) { return (p.loss_pct || 0) > 0; }).length;
            
            var currentAvg = avgValues[avgValues.length - 1] || 0;
            var minLatency = minValues.length ? Math.min.apply(null, minValues) : 0;
            var maxLatency = maxValues.length ? Math.max.apply(null, maxValues) : 0;
            var avgLoss = (lossCount / validPoints.length * 100);
            
            html += '<div class="row" style="margin-top: 10px;">' +
                '<div class="col-xs-3 text-center"><div style="font-size: 18px; font-weight: bold; color: #337ab7;">' + currentAvg.toFixed(2) + '</div><div style="font-size: 11px; color: #999;">Current (ms)</div></div>' +
                '<div class="col-xs-3 text-center"><div style="font-size: 18px; font-weight: bold; color: #5cb85c;">' + minLatency.toFixed(2) + '</div><div style="font-size: 11px; color: #999;">Min (ms)</div></div>' +
                '<div class="col-xs-3 text-center"><div style="font-size: 18px; font-weight: bold; color: #f0ad4e;">' + maxLatency.toFixed(2) + '</div><div style="font-size: 11px; color: #999;">Max (ms)</div></div>' +
                '<div class="col-xs-3 text-center"><div style="font-size: 18px; font-weight: bold; color: ' + (avgLoss > 0 ? '#d9534f' : '#5cb85c') + ';">' + avgLoss.toFixed(1) + '%</div><div style="font-size: 11px; color: #999;">Loss</div></div>' +
                '</div>';
        }
        
        container.innerHTML = html;
        
        // Setup time range buttons
        var btnGroup = document.getElementById('roundtrip-timerange-' + config.deviceId);
        btnGroup.querySelectorAll('button').forEach(function(btn) {
            if (parseInt(btn.dataset.range) === currentTimeRange) {
                btn.classList.remove('btn-default');
                btn.classList.add('btn-primary');
            }
            btn.onclick = function() {
                currentTimeRange = parseInt(this.dataset.range);
                loadAndShowChart();
            };
        });
        
        if (validPoints.length === 0) {
            document.getElementById('roundtrip-chart-' + config.deviceId).innerHTML = 
                '<p class="text-muted" style="text-align: center; padding: 40px;">No data available for this time range.</p>';
            return;
        }
        
        // Draw D3 chart
        drawD3Chart(validPoints);
    }
    
    function drawD3Chart(points) {
        var chartContainer = document.getElementById('roundtrip-chart-' + config.deviceId);
        var width = chartContainer.offsetWidth;
        var height = 200;
        var margin = { top: 20, right: 30, bottom: 30, left: 50 };
        var innerWidth = width - margin.left - margin.right;
        var innerHeight = height - margin.top - margin.bottom;
        
        // Clear previous
        d3.select(chartContainer).selectAll('*').remove();
        
        var svg = d3.select(chartContainer)
            .append('svg')
            .attr('width', width)
            .attr('height', height);
        
        var g = svg.append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');
        
        // Scales
        var xExtent = d3.extent(points, function(d) { return new Date(d.ts); });
        var yMax = d3.max(points, function(d) { return d.max_ms || d.avg_ms || 0; }) * 1.1;
        
        var xScale = d3.scaleTime().domain(xExtent).range([0, innerWidth]);
        var yScale = d3.scaleLinear().domain([0, yMax]).range([innerHeight, 0]);
        
        // Gradient
        var gradient = svg.append('defs')
            .append('linearGradient')
            .attr('id', 'smoke-gradient-' + config.deviceId)
            .attr('x1', '0%').attr('y1', '0%')
            .attr('x2', '0%').attr('y2', '100%');
        gradient.append('stop').attr('offset', '0%').attr('stop-color', '#3b82f6').attr('stop-opacity', 0.6);
        gradient.append('stop').attr('offset', '100%').attr('stop-color', '#1d4ed8').attr('stop-opacity', 0.2);
        
        // Smoke area
        var area = d3.area()
            .x(function(d) { return xScale(new Date(d.ts)); })
            .y0(function(d) { return yScale(d.min_ms || d.avg_ms || 0); })
            .y1(function(d) { return yScale(d.max_ms || d.avg_ms || 0); })
            .curve(d3.curveMonotoneX);
        
        g.append('path')
            .datum(points)
            .attr('fill', 'url(#smoke-gradient-' + config.deviceId + ')')
            .attr('d', area);
        
        // Average line
        var line = d3.line()
            .x(function(d) { return xScale(new Date(d.ts)); })
            .y(function(d) { return yScale(d.avg_ms || 0); })
            .curve(d3.curveMonotoneX);
        
        g.append('path')
            .datum(points)
            .attr('fill', 'none')
            .attr('stroke', '#2563eb')
            .attr('stroke-width', 2)
            .attr('d', line);
        
        // Loss markers
        var lossPoints = points.filter(function(p) { return (p.loss_pct || 0) > 0; });
        g.selectAll('.loss-marker')
            .data(lossPoints)
            .enter()
            .append('circle')
            .attr('cx', function(d) { return xScale(new Date(d.ts)); })
            .attr('cy', function(d) { return yScale(d.avg_ms || 0); })
            .attr('r', function(d) { return Math.min(8, 3 + (d.loss_pct || 0) / 10); })
            .attr('fill', '#ef4444')
            .attr('opacity', 0.7);
        
        // X axis - use local timezone
        var formatTime = function(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        };
        g.append('g')
            .attr('transform', 'translate(0,' + innerHeight + ')')
            .call(d3.axisBottom(xScale).ticks(6).tickFormat(formatTime))
            .selectAll('text').attr('fill', '#666');
        
        // Y axis
        g.append('g')
            .call(d3.axisLeft(yScale).ticks(5).tickFormat(function(d) { return d + 'ms'; }))
            .selectAll('text').attr('fill', '#666');
        
        // Tooltip elements
        var hoverLine = g.append('line')
            .attr('y1', 0).attr('y2', innerHeight)
            .attr('stroke', '#6b7280').attr('stroke-width', 1)
            .attr('stroke-dasharray', '4,4').style('opacity', 0);
        
        var hoverDot = g.append('circle')
            .attr('r', 6).attr('fill', '#3b82f6')
            .attr('stroke', '#fff').attr('stroke-width', 2)
            .style('opacity', 0);
        
        var tooltip = d3.select(chartContainer)
            .append('div')
            .style('position', 'absolute')
            .style('background', 'rgba(0,0,0,0.85)')
            .style('color', '#fff')
            .style('padding', '8px 12px')
            .style('border-radius', '4px')
            .style('font-size', '12px')
            .style('pointer-events', 'none')
            .style('opacity', 0)
            .style('z-index', 100);
        
        var bisect = d3.bisector(function(d) { return new Date(d.ts); }).left;
        
        // Overlay for mouse events
        g.append('rect')
            .attr('width', innerWidth)
            .attr('height', innerHeight)
            .attr('fill', 'transparent')
            .on('mousemove', function(event) {
                var mouseX = d3.pointer(event)[0];
                var x0 = xScale.invert(mouseX);
                var i = bisect(points, x0, 1);
                var d0 = points[i - 1];
                var d1 = points[i];
                if (!d0) return;
                var d = d1 && (x0 - new Date(d0.ts) > new Date(d1.ts) - x0) ? d1 : d0;
                
                var xPos = xScale(new Date(d.ts));
                var yPos = yScale(d.avg_ms || 0);
                
                hoverLine.attr('x1', xPos).attr('x2', xPos).style('opacity', 1);
                hoverDot.attr('cx', xPos).attr('cy', yPos).style('opacity', 1);
                
                var tooltipHtml = '<div style="margin-bottom: 4px; color: #999;">' + new Date(d.ts).toLocaleTimeString() + '</div>' +
                    '<div><span style="color: #60a5fa;">Avg:</span> ' + (d.avg_ms ? d.avg_ms.toFixed(2) : '-') + ' ms</div>' +
                    '<div><span style="color: #34d399;">Min:</span> ' + (d.min_ms ? d.min_ms.toFixed(2) : '-') + ' ms</div>' +
                    '<div><span style="color: #fbbf24;">Max:</span> ' + (d.max_ms ? d.max_ms.toFixed(2) : '-') + ' ms</div>';
                if ((d.loss_pct || 0) > 0) {
                    tooltipHtml += '<div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #444;"><span style="color: #f87171;">Loss:</span> ' + d.loss_pct.toFixed(1) + '%</div>';
                }
                
                tooltip.html(tooltipHtml)
                    .style('left', (xPos + margin.left + 15) + 'px')
                    .style('top', (yPos + margin.top - 10) + 'px')
                    .style('opacity', 1);
                
                if (xPos > innerWidth - 150) {
                    tooltip.style('left', (xPos + margin.left - 130) + 'px');
                }
            })
            .on('mouseleave', function() {
                hoverLine.style('opacity', 0);
                hoverDot.style('opacity', 0);
                tooltip.style('opacity', 0);
            });
    }
})();
</script>
