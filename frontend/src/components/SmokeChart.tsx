'use client';

import { useEffect, useRef, useState, useMemo } from 'react';
import * as d3 from 'd3';
import type { PingPoint } from '@/lib/api';

interface SmokeChartProps {
  points: PingPoint[];
  width?: number;
  height?: number;
}

interface TooltipData {
  x: number;
  y: number;
  time: Date;
  values: { rtt: number; lost: boolean }[];
}

export default function SmokeChart({ points, height = 350 }: SmokeChartProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const svgRef = useRef<SVGSVGElement>(null);
  const [width, setWidth] = useState(800);
  const [tooltip, setTooltip] = useState<TooltipData | null>(null);

  // Detect if we have individual RTT points or aggregated data
  const isIndividualData = useMemo(() => {
    return points.length > 0 && points[0].rtt_ms !== undefined;
  }, [points]);

  useEffect(() => {
    const updateWidth = () => {
      if (containerRef.current) {
        setWidth(containerRef.current.clientWidth);
      }
    };
    updateWidth();
    window.addEventListener('resize', updateWidth);
    return () => window.removeEventListener('resize', updateWidth);
  }, []);

  useEffect(() => {
    if (!svgRef.current || points.length === 0) return;

    const svg = d3.select(svgRef.current);
    svg.selectAll('*').remove();

    const margin = { top: 20, right: 30, bottom: 40, left: 60 };
    const innerWidth = width - margin.left - margin.right;
    const innerHeight = height - margin.top - margin.bottom;

    const g = svg
      .append('g')
      .attr('transform', `translate(${margin.left},${margin.top})`);

    if (isIndividualData) {
      // Group points by timestamp
      const groupedByTime = new Map<string, { ts: Date; rtts: number[]; lostCount: number }>();
      points.forEach(p => {
        const key = p.ts;
        if (!groupedByTime.has(key)) {
          groupedByTime.set(key, { ts: new Date(p.ts), rtts: [], lostCount: 0 });
        }
        const group = groupedByTime.get(key)!;
        if (p.lost) {
          group.lostCount++;
        } else if (p.rtt_ms !== null && p.rtt_ms !== undefined) {
          group.rtts.push(p.rtt_ms);
        }
      });

      // Convert to sorted array with sorted RTTs per timestamp
      const timePoints = Array.from(groupedByTime.values())
        .sort((a, b) => a.ts.getTime() - b.ts.getTime())
        .map(g => ({
          ts: g.ts,
          rtts: g.rtts.sort((a, b) => a - b), // Sort RTTs low to high
          lostCount: g.lostCount,
        }));

      if (timePoints.length === 0) return;

      const xExtent = d3.extent(timePoints, d => d.ts) as [Date, Date];
      const allRtts = timePoints.flatMap(p => p.rtts);
      const yMax = d3.max(allRtts) || 100;

      const xScale = d3.scaleTime().domain(xExtent).range([0, innerWidth]);
      const yScale = d3.scaleLinear().domain([0, yMax * 1.1]).range([innerHeight, 0]);

      // Colors from darkest (lowest RTT) to lightest (highest RTT)
      const smokeColors = ['#1e40af', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'];

      // Draw filled areas between each RTT level (from bottom up)
      // First, draw area from 0 to lowest RTT
      for (let level = 0; level < 5; level++) {
        const areaData = timePoints.filter(p => p.rtts.length > level);
        if (areaData.length < 2) continue;

        const area = d3.area<typeof areaData[0]>()
          .x(d => xScale(d.ts))
          .y0(d => level === 0 ? innerHeight : yScale(d.rtts[level - 1] || 0))
          .y1(d => yScale(d.rtts[level] || 0))
          .curve(d3.curveMonotoneX);

        g.append('path')
          .datum(areaData)
          .attr('fill', smokeColors[level])
          .attr('opacity', 0.3)
          .attr('d', area);
      }

      // Draw lines for each RTT level
      for (let level = 0; level < 5; level++) {
        const lineData = timePoints.filter(p => p.rtts.length > level);
        if (lineData.length < 2) continue;

        const line = d3.line<typeof lineData[0]>()
          .x(d => xScale(d.ts))
          .y(d => yScale(d.rtts[level] || 0))
          .curve(d3.curveMonotoneX);

        g.append('path')
          .datum(lineData)
          .attr('fill', 'none')
          .attr('stroke', smokeColors[level])
          .attr('stroke-width', 1)
          .attr('opacity', 0.6)
          .attr('d', line);
      }

      // Draw lost packet markers at bottom
      const lostTimePoints = timePoints.filter(p => p.lostCount > 0);
      g.selectAll('.lost-marker')
        .data(lostTimePoints)
        .enter()
        .append('rect')
        .attr('x', d => xScale(d.ts) - 2)
        .attr('y', innerHeight - 4)
        .attr('width', 4)
        .attr('height', 4)
        .attr('fill', '#ef4444')
        .attr('opacity', 0.8);

      // Draw axes
      const formatTime = (date: Date) => date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
      g.append('g')
        .attr('transform', `translate(0,${innerHeight})`)
        .call(d3.axisBottom(xScale).ticks(6).tickFormat(d => formatTime(d as Date)))
        .selectAll('text').attr('fill', '#9ca3af');

      g.append('g')
        .call(d3.axisLeft(yScale).ticks(5).tickFormat(d => `${d}ms`))
        .selectAll('text').attr('fill', '#9ca3af');

      // Hover overlay
      const hoverLine = g.append('line')
        .attr('y1', 0).attr('y2', innerHeight)
        .attr('stroke', '#6b7280').attr('stroke-width', 1)
        .attr('stroke-dasharray', '4,4').style('opacity', 0);

      const bisect = d3.bisector<typeof timePoints[0], Date>(d => d.ts).left;

      g.append('rect')
        .attr('width', innerWidth).attr('height', innerHeight)
        .attr('fill', 'transparent')
        .on('mousemove', function(event) {
          const [mouseX] = d3.pointer(event);
          const x0 = xScale.invert(mouseX);
          const i = bisect(timePoints, x0, 1);
          const d0 = timePoints[i - 1];
          const d1 = timePoints[i];
          if (!d0) return;
          const d = d1 && (x0.getTime() - d0.ts.getTime() > d1.ts.getTime() - x0.getTime()) ? d1 : d0;

          const xPos = xScale(d.ts);
          hoverLine.attr('x1', xPos).attr('x2', xPos).style('opacity', 1);

          setTooltip({
            x: xPos + margin.left,
            y: margin.top + 20,
            time: d.ts,
            values: d.rtts.map(rtt => ({ rtt, lost: false })).concat(
              Array(d.lostCount).fill({ rtt: 0, lost: true })
            ),
          });
        })
        .on('mouseleave', function() {
          hoverLine.style('opacity', 0);
          setTooltip(null);
        });

    } else {
      // Aggregated data - min/avg/max smoke band
      const validPoints = points.filter(p => p.avg_ms !== null);
      if (validPoints.length === 0) return;

      const xExtent = d3.extent(validPoints, d => new Date(d.ts)) as [Date, Date];
      const yMax = d3.max(validPoints, d => d.max_ms || d.avg_ms || 0) || 100;

      const xScale = d3.scaleTime().domain(xExtent).range([0, innerWidth]);
      const yScale = d3.scaleLinear().domain([0, yMax * 1.1]).range([innerHeight, 0]);

      // Gradient
      const gradient = svg.append('defs')
        .append('linearGradient')
        .attr('id', 'smoke-gradient')
        .attr('x1', '0%').attr('y1', '0%')
        .attr('x2', '0%').attr('y2', '100%');
      gradient.append('stop').attr('offset', '0%').attr('stop-color', '#3b82f6').attr('stop-opacity', 0.6);
      gradient.append('stop').attr('offset', '100%').attr('stop-color', '#1d4ed8').attr('stop-opacity', 0.2);

      // Smoke area
      const areaGenerator = d3.area<PingPoint>()
        .x(d => xScale(new Date(d.ts)))
        .y0(d => yScale(d.min_ms || d.avg_ms || 0))
        .y1(d => yScale(d.max_ms || d.avg_ms || 0))
        .curve(d3.curveMonotoneX);

      g.append('path').datum(validPoints).attr('fill', 'url(#smoke-gradient)').attr('d', areaGenerator);

      // Average line
      const lineGenerator = d3.line<PingPoint>()
        .x(d => xScale(new Date(d.ts)))
        .y(d => yScale(d.avg_ms || 0))
        .curve(d3.curveMonotoneX);

      g.append('path').datum(validPoints)
        .attr('fill', 'none').attr('stroke', '#2563eb').attr('stroke-width', 2).attr('d', lineGenerator);

      // Loss markers
      const lossPoints = validPoints.filter(p => (p.loss_pct || 0) > 0);
      g.selectAll('.loss-marker')
        .data(lossPoints)
        .enter()
        .append('circle')
        .attr('cx', d => xScale(new Date(d.ts)))
        .attr('cy', d => yScale(d.avg_ms || 0))
        .attr('r', d => Math.min(8, 3 + (d.loss_pct || 0) / 10))
        .attr('fill', '#ef4444')
        .attr('opacity', 0.7);

      // Axes
      const formatTime = (date: Date) => date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
      g.append('g')
        .attr('transform', `translate(0,${innerHeight})`)
        .call(d3.axisBottom(xScale).ticks(6).tickFormat(d => formatTime(d as Date)))
        .selectAll('text').attr('fill', '#9ca3af');

      g.append('g')
        .call(d3.axisLeft(yScale).ticks(5).tickFormat(d => `${d}ms`))
        .selectAll('text').attr('fill', '#9ca3af');

      // Hover
      const hoverLine = g.append('line')
        .attr('y1', 0).attr('y2', innerHeight)
        .attr('stroke', '#6b7280').attr('stroke-width', 1)
        .attr('stroke-dasharray', '4,4').style('opacity', 0);

      const hoverDot = g.append('circle')
        .attr('r', 6).attr('fill', '#3b82f6')
        .attr('stroke', '#fff').attr('stroke-width', 2).style('opacity', 0);

      const bisect = d3.bisector<PingPoint, Date>(d => new Date(d.ts)).left;

      g.append('rect')
        .attr('width', innerWidth).attr('height', innerHeight)
        .attr('fill', 'transparent')
        .on('mousemove', function(event) {
          const [mouseX] = d3.pointer(event);
          const x0 = xScale.invert(mouseX);
          const i = bisect(validPoints, x0, 1);
          const d0 = validPoints[i - 1];
          const d1 = validPoints[i];
          if (!d0) return;
          const d = d1 && (x0.getTime() - new Date(d0.ts).getTime() > new Date(d1.ts).getTime() - x0.getTime()) ? d1 : d0;

          const xPos = xScale(new Date(d.ts));
          const yPos = yScale(d.avg_ms || 0);

          hoverLine.attr('x1', xPos).attr('x2', xPos).style('opacity', 1);
          hoverDot.attr('cx', xPos).attr('cy', yPos).style('opacity', 1);

          setTooltip({
            x: xPos + margin.left,
            y: yPos + margin.top,
            time: new Date(d.ts),
            values: [
              { rtt: d.min_ms || 0, lost: false },
              { rtt: d.avg_ms || 0, lost: false },
              { rtt: d.max_ms || 0, lost: false },
            ],
          });
        })
        .on('mouseleave', function() {
          hoverLine.style('opacity', 0);
          hoverDot.style('opacity', 0);
          setTooltip(null);
        });
    }

    // Y axis label
    g.append('text')
      .attr('transform', 'rotate(-90)')
      .attr('y', -40)
      .attr('x', -innerHeight / 2)
      .attr('text-anchor', 'middle')
      .attr('fill', '#9ca3af')
      .attr('font-size', '12px')
      .text('Latency (ms)');

  }, [points, width, height, isIndividualData]);

  const formatTime = (date: Date) => date.toLocaleTimeString();

  return (
    <div ref={containerRef} className="w-full relative">
      <svg ref={svgRef} width={width} height={height} className="rounded-lg" />
      
      {tooltip && (
        <div
          className="absolute pointer-events-none z-20 bg-zinc-900/95 border border-zinc-700 rounded-lg px-3 py-2 shadow-xl backdrop-blur-sm"
          style={{
            left: tooltip.x + 15,
            top: tooltip.y - 10,
            transform: tooltip.x > width - 180 ? 'translateX(-100%) translateX(-30px)' : undefined,
          }}
        >
          <p className="text-xs text-zinc-400 mb-1.5 font-mono">{formatTime(tooltip.time)}</p>
          <div className="space-y-1">
            {isIndividualData ? (
              <>
                {tooltip.values.filter(v => !v.lost).length > 0 && (
                  <div className="flex items-center justify-between gap-4">
                    <span className="text-xs text-zinc-500">RTT</span>
                    <span className="text-sm font-medium text-blue-400">
                      {tooltip.values.filter(v => !v.lost).map(v => v.rtt.toFixed(2)).join(', ')} ms
                    </span>
                  </div>
                )}
                {tooltip.values.filter(v => v.lost).length > 0 && (
                  <div className="flex items-center justify-between gap-4 pt-1 border-t border-zinc-700">
                    <span className="text-xs text-zinc-500">Lost</span>
                    <span className="text-sm font-medium text-red-400">{tooltip.values.filter(v => v.lost).length} packets</span>
                  </div>
                )}
              </>
            ) : (
              <>
                <div className="flex items-center justify-between gap-4">
                  <span className="text-xs text-zinc-500">Avg</span>
                  <span className="text-sm font-medium text-blue-400">{tooltip.values[1]?.rtt.toFixed(2) ?? '-'} ms</span>
                </div>
                <div className="flex items-center justify-between gap-4">
                  <span className="text-xs text-zinc-500">Min</span>
                  <span className="text-sm font-medium text-emerald-400">{tooltip.values[0]?.rtt.toFixed(2) ?? '-'} ms</span>
                </div>
                <div className="flex items-center justify-between gap-4">
                  <span className="text-xs text-zinc-500">Max</span>
                  <span className="text-sm font-medium text-amber-400">{tooltip.values[2]?.rtt.toFixed(2) ?? '-'} ms</span>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
