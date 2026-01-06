'use client';

import { useEffect, useRef } from 'react';
import * as d3 from 'd3';
import type { PingPoint } from '@/lib/api';

interface SmokeChartProps {
  points: PingPoint[];
  width?: number;
  height?: number;
}

export default function SmokeChart({ points, width = 800, height = 300 }: SmokeChartProps) {
  const svgRef = useRef<SVGSVGElement>(null);

  useEffect(() => {
    if (!svgRef.current || points.length === 0) return;

    const svg = d3.select(svgRef.current);
    svg.selectAll('*').remove();

    const margin = { top: 20, right: 30, bottom: 40, left: 50 };
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

  }, [points, width, height]);

  return (
    <svg
      ref={svgRef}
      width={width}
      height={height}
      className="bg-zinc-900 rounded-lg"
    />
  );
}
