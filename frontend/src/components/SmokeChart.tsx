'use client';

import { useEffect, useRef, useState } from 'react';
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
  point: PingPoint;
}

export default function SmokeChart({ points, height = 350 }: SmokeChartProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const svgRef = useRef<SVGSVGElement>(null);
  const [width, setWidth] = useState(800);
  const [tooltip, setTooltip] = useState<TooltipData | null>(null);

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

    const validPoints = points.filter(p => p.avg_ms !== null);
    
    if (validPoints.length === 0) return;

    const xExtent = d3.extent(validPoints, d => new Date(d.ts)) as [Date, Date];
    const yMax = d3.max(validPoints, d => d.max_ms || d.avg_ms || 0) || 100;

    const xScale = d3.scaleTime()
      .domain(xExtent)
      .range([0, innerWidth]);

    const yScale = d3.scaleLinear()
      .domain([0, yMax * 1.1])
      .range([innerHeight, 0]);

    // Draw the "smoke" area between min and max
    const areaGenerator = d3.area<PingPoint>()
      .x(d => xScale(new Date(d.ts)))
      .y0(d => yScale(d.min_ms || d.avg_ms || 0))
      .y1(d => yScale(d.max_ms || d.avg_ms || 0))
      .curve(d3.curveMonotoneX);

    // Gradient for smoke effect
    const gradient = svg.append('defs')
      .append('linearGradient')
      .attr('id', 'smoke-gradient')
      .attr('x1', '0%')
      .attr('y1', '0%')
      .attr('x2', '0%')
      .attr('y2', '100%');

    gradient.append('stop')
      .attr('offset', '0%')
      .attr('stop-color', '#3b82f6')
      .attr('stop-opacity', 0.6);

    gradient.append('stop')
      .attr('offset', '100%')
      .attr('stop-color', '#1d4ed8')
      .attr('stop-opacity', 0.2);

    // Draw smoke area
    g.append('path')
      .datum(validPoints)
      .attr('fill', 'url(#smoke-gradient)')
      .attr('d', areaGenerator);

    // Draw average line
    const lineGenerator = d3.line<PingPoint>()
      .x(d => xScale(new Date(d.ts)))
      .y(d => yScale(d.avg_ms || 0))
      .curve(d3.curveMonotoneX);

    g.append('path')
      .datum(validPoints)
      .attr('fill', 'none')
      .attr('stroke', '#2563eb')
      .attr('stroke-width', 2)
      .attr('d', lineGenerator);

    // Draw packet loss indicators
    const lossPoints = validPoints.filter(p => (p.loss_pct || 0) > 0);
    g.selectAll('.loss-marker')
      .data(lossPoints)
      .enter()
      .append('circle')
      .attr('class', 'loss-marker')
      .attr('cx', d => xScale(new Date(d.ts)))
      .attr('cy', d => yScale(d.avg_ms || 0))
      .attr('r', d => Math.min(8, 3 + (d.loss_pct || 0) / 10))
      .attr('fill', '#ef4444')
      .attr('opacity', 0.7);

    // X axis
    g.append('g')
      .attr('transform', `translate(0,${innerHeight})`)
      .call(d3.axisBottom(xScale).ticks(6).tickFormat(d => d3.timeFormat('%H:%M:%S')(d as Date)))
      .selectAll('text')
      .attr('fill', '#9ca3af');

    // Y axis
    g.append('g')
      .call(d3.axisLeft(yScale).ticks(5).tickFormat(d => `${d}ms`))
      .selectAll('text')
      .attr('fill', '#9ca3af');

    // Y axis label
    g.append('text')
      .attr('transform', 'rotate(-90)')
      .attr('y', -40)
      .attr('x', -innerHeight / 2)
      .attr('text-anchor', 'middle')
      .attr('fill', '#9ca3af')
      .attr('font-size', '12px')
      .text('Latency (ms)');

    // Vertical hover line
    const hoverLine = g.append('line')
      .attr('class', 'hover-line')
      .attr('y1', 0)
      .attr('y2', innerHeight)
      .attr('stroke', '#6b7280')
      .attr('stroke-width', 1)
      .attr('stroke-dasharray', '4,4')
      .style('opacity', 0);

    // Hover dot
    const hoverDot = g.append('circle')
      .attr('class', 'hover-dot')
      .attr('r', 6)
      .attr('fill', '#3b82f6')
      .attr('stroke', '#fff')
      .attr('stroke-width', 2)
      .style('opacity', 0);

    // Bisector for finding closest point
    const bisect = d3.bisector<PingPoint, Date>(d => new Date(d.ts)).left;

    // Overlay for mouse events
    g.append('rect')
      .attr('class', 'overlay')
      .attr('width', innerWidth)
      .attr('height', innerHeight)
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

        hoverLine
          .attr('x1', xPos)
          .attr('x2', xPos)
          .style('opacity', 1);

        hoverDot
          .attr('cx', xPos)
          .attr('cy', yPos)
          .style('opacity', 1);

        setTooltip({
          x: xPos + margin.left,
          y: yPos + margin.top,
          point: d,
        });
      })
      .on('mouseleave', function() {
        hoverLine.style('opacity', 0);
        hoverDot.style('opacity', 0);
        setTooltip(null);
      });

  }, [points, width, height]);

  const formatTime = (ts: string) => {
    const date = new Date(ts);
    return date.toLocaleTimeString();
  };

  return (
    <div ref={containerRef} className="w-full relative">
      <svg
        ref={svgRef}
        width={width}
        height={height}
        className="rounded-lg"
      />
      
      {/* Tooltip */}
      {tooltip && (
        <div
          className="absolute pointer-events-none z-20 bg-zinc-900/95 border border-zinc-700 rounded-lg px-3 py-2 shadow-xl backdrop-blur-sm"
          style={{
            left: tooltip.x + 15,
            top: tooltip.y - 10,
            transform: tooltip.x > width - 180 ? 'translateX(-100%) translateX(-30px)' : undefined,
          }}
        >
          <p className="text-xs text-zinc-400 mb-1.5 font-mono">{formatTime(tooltip.point.ts)}</p>
          <div className="space-y-1">
            <div className="flex items-center justify-between gap-4">
              <span className="text-xs text-zinc-500">Avg</span>
              <span className="text-sm font-medium text-blue-400">{tooltip.point.avg_ms?.toFixed(2) ?? '-'} ms</span>
            </div>
            <div className="flex items-center justify-between gap-4">
              <span className="text-xs text-zinc-500">Min</span>
              <span className="text-sm font-medium text-emerald-400">{tooltip.point.min_ms?.toFixed(2) ?? '-'} ms</span>
            </div>
            <div className="flex items-center justify-between gap-4">
              <span className="text-xs text-zinc-500">Max</span>
              <span className="text-sm font-medium text-amber-400">{tooltip.point.max_ms?.toFixed(2) ?? '-'} ms</span>
            </div>
            {(tooltip.point.loss_pct ?? 0) > 0 && (
              <div className="flex items-center justify-between gap-4 pt-1 border-t border-zinc-700">
                <span className="text-xs text-zinc-500">Loss</span>
                <span className="text-sm font-medium text-red-400">{tooltip.point.loss_pct?.toFixed(1)}%</span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
