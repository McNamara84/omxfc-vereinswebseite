import {
  __commonJS
} from "./chunk-L6OFPWCY.js";

// node_modules/leaflet.markercluster/dist/leaflet.markercluster-src.js
var require_leaflet_markercluster_src = __commonJS({
  "node_modules/leaflet.markercluster/dist/leaflet.markercluster-src.js"(exports, module) {
    (function(global, factory) {
      typeof exports === "object" && typeof module !== "undefined" ? factory(exports) : typeof define === "function" && define.amd ? define(["exports"], factory) : (global = global || self, factory((global.Leaflet = global.Leaflet || {}, global.Leaflet.markercluster = {})));
    })(exports, function(exports2) {
      "use strict";
      var MarkerClusterGroup = L.MarkerClusterGroup = L.FeatureGroup.extend({
        options: {
          maxClusterRadius: 80,
          //A cluster will cover at most this many pixels from its center
          iconCreateFunction: null,
          clusterPane: L.Marker.prototype.options.pane,
          spiderfyOnEveryZoom: false,
          spiderfyOnMaxZoom: true,
          showCoverageOnHover: true,
          zoomToBoundsOnClick: true,
          singleMarkerMode: false,
          disableClusteringAtZoom: null,
          // Setting this to false prevents the removal of any clusters outside of the viewpoint, which
          // is the default behaviour for performance reasons.
          removeOutsideVisibleBounds: true,
          // Set to false to disable all animations (zoom and spiderfy).
          // If false, option animateAddingMarkers below has no effect.
          // If L.DomUtil.TRANSITION is falsy, this option has no effect.
          animate: true,
          //Whether to animate adding markers after adding the MarkerClusterGroup to the map
          // If you are adding individual markers set to true, if adding bulk markers leave false for massive performance gains.
          animateAddingMarkers: false,
          // Make it possible to provide custom function to calculate spiderfy shape positions
          spiderfyShapePositions: null,
          //Increase to increase the distance away that spiderfied markers appear from the center
          spiderfyDistanceMultiplier: 1,
          // Make it possible to specify a polyline options on a spider leg
          spiderLegPolylineOptions: { weight: 1.5, color: "#222", opacity: 0.5 },
          // When bulk adding layers, adds markers in chunks. Means addLayers may not add all the layers in the call, others will be loaded during setTimeouts
          chunkedLoading: false,
          chunkInterval: 200,
          // process markers for a maximum of ~ n milliseconds (then trigger the chunkProgress callback)
          chunkDelay: 50,
          // at the end of each interval, give n milliseconds back to system/browser
          chunkProgress: null,
          // progress callback: function(processed, total, elapsed) (e.g. for a progress indicator)
          //Options to pass to the L.Polygon constructor
          polygonOptions: {}
        },
        initialize: function(options) {
          L.Util.setOptions(this, options);
          if (!this.options.iconCreateFunction) {
            this.options.iconCreateFunction = this._defaultIconCreateFunction;
          }
          this._featureGroup = L.featureGroup();
          this._featureGroup.addEventParent(this);
          this._nonPointGroup = L.featureGroup();
          this._nonPointGroup.addEventParent(this);
          this._inZoomAnimation = 0;
          this._needsClustering = [];
          this._needsRemoving = [];
          this._currentShownBounds = null;
          this._queue = [];
          this._childMarkerEventHandlers = {
            "dragstart": this._childMarkerDragStart,
            "move": this._childMarkerMoved,
            "dragend": this._childMarkerDragEnd
          };
          var animate = L.DomUtil.TRANSITION && this.options.animate;
          L.extend(this, animate ? this._withAnimation : this._noAnimation);
          this._markerCluster = animate ? L.MarkerCluster : L.MarkerClusterNonAnimated;
        },
        addLayer: function(layer) {
          if (layer instanceof L.LayerGroup) {
            return this.addLayers([layer]);
          }
          if (!layer.getLatLng) {
            this._nonPointGroup.addLayer(layer);
            this.fire("layeradd", { layer });
            return this;
          }
          if (!this._map) {
            this._needsClustering.push(layer);
            this.fire("layeradd", { layer });
            return this;
          }
          if (this.hasLayer(layer)) {
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
          }
          this._addLayer(layer, this._maxZoom);
          this.fire("layeradd", { layer });
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          var visibleLayer = layer, currentZoom = this._zoom;
          if (layer.__parent) {
            while (visibleLayer.__parent._zoom >= currentZoom) {
              visibleLayer = visibleLayer.__parent;
            }
          }
          if (this._currentShownBounds.contains(visibleLayer.getLatLng())) {
            if (this.options.animateAddingMarkers) {
              this._animationAddLayer(layer, visibleLayer);
            } else {
              this._animationAddLayerNonAnimated(layer, visibleLayer);
            }
          }
          return this;
        },
        removeLayer: function(layer) {
          if (layer instanceof L.LayerGroup) {
            return this.removeLayers([layer]);
          }
          if (!layer.getLatLng) {
            this._nonPointGroup.removeLayer(layer);
            this.fire("layerremove", { layer });
            return this;
          }
          if (!this._map) {
            if (!this._arraySplice(this._needsClustering, layer) && this.hasLayer(layer)) {
              this._needsRemoving.push({ layer, latlng: layer._latlng });
            }
            this.fire("layerremove", { layer });
            return this;
          }
          if (!layer.__parent) {
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
            this._unspiderfyLayer(layer);
          }
          this._removeLayer(layer, true);
          this.fire("layerremove", { layer });
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          layer.off(this._childMarkerEventHandlers, this);
          if (this._featureGroup.hasLayer(layer)) {
            this._featureGroup.removeLayer(layer);
            if (layer.clusterShow) {
              layer.clusterShow();
            }
          }
          return this;
        },
        //Takes an array of markers and adds them in bulk
        addLayers: function(layersArray, skipLayerAddEvent) {
          if (!L.Util.isArray(layersArray)) {
            return this.addLayer(layersArray);
          }
          var fg = this._featureGroup, npg = this._nonPointGroup, chunked = this.options.chunkedLoading, chunkInterval = this.options.chunkInterval, chunkProgress = this.options.chunkProgress, l = layersArray.length, offset = 0, originalArray = true, m;
          if (this._map) {
            var started = (/* @__PURE__ */ new Date()).getTime();
            var process = L.bind(function() {
              var start = (/* @__PURE__ */ new Date()).getTime();
              if (this._map && this._unspiderfy) {
                this._unspiderfy();
              }
              for (; offset < l; offset++) {
                if (chunked && offset % 200 === 0) {
                  var elapsed = (/* @__PURE__ */ new Date()).getTime() - start;
                  if (elapsed > chunkInterval) {
                    break;
                  }
                }
                m = layersArray[offset];
                if (m instanceof L.LayerGroup) {
                  if (originalArray) {
                    layersArray = layersArray.slice();
                    originalArray = false;
                  }
                  this._extractNonGroupLayers(m, layersArray);
                  l = layersArray.length;
                  continue;
                }
                if (!m.getLatLng) {
                  npg.addLayer(m);
                  if (!skipLayerAddEvent) {
                    this.fire("layeradd", { layer: m });
                  }
                  continue;
                }
                if (this.hasLayer(m)) {
                  continue;
                }
                this._addLayer(m, this._maxZoom);
                if (!skipLayerAddEvent) {
                  this.fire("layeradd", { layer: m });
                }
                if (m.__parent) {
                  if (m.__parent.getChildCount() === 2) {
                    var markers = m.__parent.getAllChildMarkers(), otherMarker = markers[0] === m ? markers[1] : markers[0];
                    fg.removeLayer(otherMarker);
                  }
                }
              }
              if (chunkProgress) {
                chunkProgress(offset, l, (/* @__PURE__ */ new Date()).getTime() - started);
              }
              if (offset === l) {
                this._topClusterLevel._recalculateBounds();
                this._refreshClustersIcons();
                this._topClusterLevel._recursivelyAddChildrenToMap(null, this._zoom, this._currentShownBounds);
              } else {
                setTimeout(process, this.options.chunkDelay);
              }
            }, this);
            process();
          } else {
            var needsClustering = this._needsClustering;
            for (; offset < l; offset++) {
              m = layersArray[offset];
              if (m instanceof L.LayerGroup) {
                if (originalArray) {
                  layersArray = layersArray.slice();
                  originalArray = false;
                }
                this._extractNonGroupLayers(m, layersArray);
                l = layersArray.length;
                continue;
              }
              if (!m.getLatLng) {
                npg.addLayer(m);
                continue;
              }
              if (this.hasLayer(m)) {
                continue;
              }
              needsClustering.push(m);
            }
          }
          return this;
        },
        //Takes an array of markers and removes them in bulk
        removeLayers: function(layersArray) {
          var i, m, l = layersArray.length, fg = this._featureGroup, npg = this._nonPointGroup, originalArray = true;
          if (!this._map) {
            for (i = 0; i < l; i++) {
              m = layersArray[i];
              if (m instanceof L.LayerGroup) {
                if (originalArray) {
                  layersArray = layersArray.slice();
                  originalArray = false;
                }
                this._extractNonGroupLayers(m, layersArray);
                l = layersArray.length;
                continue;
              }
              this._arraySplice(this._needsClustering, m);
              npg.removeLayer(m);
              if (this.hasLayer(m)) {
                this._needsRemoving.push({ layer: m, latlng: m._latlng });
              }
              this.fire("layerremove", { layer: m });
            }
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
            var layersArray2 = layersArray.slice(), l2 = l;
            for (i = 0; i < l2; i++) {
              m = layersArray2[i];
              if (m instanceof L.LayerGroup) {
                this._extractNonGroupLayers(m, layersArray2);
                l2 = layersArray2.length;
                continue;
              }
              this._unspiderfyLayer(m);
            }
          }
          for (i = 0; i < l; i++) {
            m = layersArray[i];
            if (m instanceof L.LayerGroup) {
              if (originalArray) {
                layersArray = layersArray.slice();
                originalArray = false;
              }
              this._extractNonGroupLayers(m, layersArray);
              l = layersArray.length;
              continue;
            }
            if (!m.__parent) {
              npg.removeLayer(m);
              this.fire("layerremove", { layer: m });
              continue;
            }
            this._removeLayer(m, true, true);
            this.fire("layerremove", { layer: m });
            if (fg.hasLayer(m)) {
              fg.removeLayer(m);
              if (m.clusterShow) {
                m.clusterShow();
              }
            }
          }
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          this._topClusterLevel._recursivelyAddChildrenToMap(null, this._zoom, this._currentShownBounds);
          return this;
        },
        //Removes all layers from the MarkerClusterGroup
        clearLayers: function() {
          if (!this._map) {
            this._needsClustering = [];
            this._needsRemoving = [];
            delete this._gridClusters;
            delete this._gridUnclustered;
          }
          if (this._noanimationUnspiderfy) {
            this._noanimationUnspiderfy();
          }
          this._featureGroup.clearLayers();
          this._nonPointGroup.clearLayers();
          this.eachLayer(function(marker) {
            marker.off(this._childMarkerEventHandlers, this);
            delete marker.__parent;
          }, this);
          if (this._map) {
            this._generateInitialClusters();
          }
          return this;
        },
        //Override FeatureGroup.getBounds as it doesn't work
        getBounds: function() {
          var bounds = new L.LatLngBounds();
          if (this._topClusterLevel) {
            bounds.extend(this._topClusterLevel._bounds);
          }
          for (var i = this._needsClustering.length - 1; i >= 0; i--) {
            bounds.extend(this._needsClustering[i].getLatLng());
          }
          bounds.extend(this._nonPointGroup.getBounds());
          return bounds;
        },
        //Overrides LayerGroup.eachLayer
        eachLayer: function(method, context) {
          var markers = this._needsClustering.slice(), needsRemoving = this._needsRemoving, thisNeedsRemoving, i, j;
          if (this._topClusterLevel) {
            this._topClusterLevel.getAllChildMarkers(markers);
          }
          for (i = markers.length - 1; i >= 0; i--) {
            thisNeedsRemoving = true;
            for (j = needsRemoving.length - 1; j >= 0; j--) {
              if (needsRemoving[j].layer === markers[i]) {
                thisNeedsRemoving = false;
                break;
              }
            }
            if (thisNeedsRemoving) {
              method.call(context, markers[i]);
            }
          }
          this._nonPointGroup.eachLayer(method, context);
        },
        //Overrides LayerGroup.getLayers
        getLayers: function() {
          var layers = [];
          this.eachLayer(function(l) {
            layers.push(l);
          });
          return layers;
        },
        //Overrides LayerGroup.getLayer, WARNING: Really bad performance
        getLayer: function(id) {
          var result = null;
          id = parseInt(id, 10);
          this.eachLayer(function(l) {
            if (L.stamp(l) === id) {
              result = l;
            }
          });
          return result;
        },
        //Returns true if the given layer is in this MarkerClusterGroup
        hasLayer: function(layer) {
          if (!layer) {
            return false;
          }
          var i, anArray = this._needsClustering;
          for (i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i] === layer) {
              return true;
            }
          }
          anArray = this._needsRemoving;
          for (i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i].layer === layer) {
              return false;
            }
          }
          return !!(layer.__parent && layer.__parent._group === this) || this._nonPointGroup.hasLayer(layer);
        },
        //Zoom down to show the given layer (spiderfying if necessary) then calls the callback
        zoomToShowLayer: function(layer, callback) {
          var map = this._map;
          if (typeof callback !== "function") {
            callback = function() {
            };
          }
          var showMarker = function() {
            if ((map.hasLayer(layer) || map.hasLayer(layer.__parent)) && !this._inZoomAnimation) {
              this._map.off("moveend", showMarker, this);
              this.off("animationend", showMarker, this);
              if (map.hasLayer(layer)) {
                callback();
              } else if (layer.__parent._icon) {
                this.once("spiderfied", callback, this);
                layer.__parent.spiderfy();
              }
            }
          };
          if (layer._icon && this._map.getBounds().contains(layer.getLatLng())) {
            callback();
          } else if (layer.__parent._zoom < Math.round(this._map._zoom)) {
            this._map.on("moveend", showMarker, this);
            this._map.panTo(layer.getLatLng());
          } else {
            this._map.on("moveend", showMarker, this);
            this.on("animationend", showMarker, this);
            layer.__parent.zoomToBounds();
          }
        },
        //Overrides FeatureGroup.onAdd
        onAdd: function(map) {
          this._map = map;
          var i, l, layer;
          if (!isFinite(this._map.getMaxZoom())) {
            throw "Map has no maxZoom specified";
          }
          this._featureGroup.addTo(map);
          this._nonPointGroup.addTo(map);
          if (!this._gridClusters) {
            this._generateInitialClusters();
          }
          this._maxLat = map.options.crs.projection.MAX_LATITUDE;
          for (i = 0, l = this._needsRemoving.length; i < l; i++) {
            layer = this._needsRemoving[i];
            layer.newlatlng = layer.layer._latlng;
            layer.layer._latlng = layer.latlng;
          }
          for (i = 0, l = this._needsRemoving.length; i < l; i++) {
            layer = this._needsRemoving[i];
            this._removeLayer(layer.layer, true);
            layer.layer._latlng = layer.newlatlng;
          }
          this._needsRemoving = [];
          this._zoom = Math.round(this._map._zoom);
          this._currentShownBounds = this._getExpandedVisibleBounds();
          this._map.on("zoomend", this._zoomEnd, this);
          this._map.on("moveend", this._moveEnd, this);
          if (this._spiderfierOnAdd) {
            this._spiderfierOnAdd();
          }
          this._bindEvents();
          l = this._needsClustering;
          this._needsClustering = [];
          this.addLayers(l, true);
        },
        //Overrides FeatureGroup.onRemove
        onRemove: function(map) {
          map.off("zoomend", this._zoomEnd, this);
          map.off("moveend", this._moveEnd, this);
          this._unbindEvents();
          this._map._mapPane.className = this._map._mapPane.className.replace(" leaflet-cluster-anim", "");
          if (this._spiderfierOnRemove) {
            this._spiderfierOnRemove();
          }
          delete this._maxLat;
          this._hideCoverage();
          this._featureGroup.remove();
          this._nonPointGroup.remove();
          this._featureGroup.clearLayers();
          this._map = null;
        },
        getVisibleParent: function(marker) {
          var vMarker = marker;
          while (vMarker && !vMarker._icon) {
            vMarker = vMarker.__parent;
          }
          return vMarker || null;
        },
        //Remove the given object from the given array
        _arraySplice: function(anArray, obj) {
          for (var i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i] === obj) {
              anArray.splice(i, 1);
              return true;
            }
          }
        },
        /**
         * Removes a marker from all _gridUnclustered zoom levels, starting at the supplied zoom.
         * @param marker to be removed from _gridUnclustered.
         * @param z integer bottom start zoom level (included)
         * @private
         */
        _removeFromGridUnclustered: function(marker, z) {
          var map = this._map, gridUnclustered = this._gridUnclustered, minZoom = Math.floor(this._map.getMinZoom());
          for (; z >= minZoom; z--) {
            if (!gridUnclustered[z].removeObject(marker, map.project(marker.getLatLng(), z))) {
              break;
            }
          }
        },
        _childMarkerDragStart: function(e) {
          e.target.__dragStart = e.target._latlng;
        },
        _childMarkerMoved: function(e) {
          if (!this._ignoreMove && !e.target.__dragStart) {
            var isPopupOpen = e.target._popup && e.target._popup.isOpen();
            this._moveChild(e.target, e.oldLatLng, e.latlng);
            if (isPopupOpen) {
              e.target.openPopup();
            }
          }
        },
        _moveChild: function(layer, from, to) {
          layer._latlng = from;
          this.removeLayer(layer);
          layer._latlng = to;
          this.addLayer(layer);
        },
        _childMarkerDragEnd: function(e) {
          var dragStart = e.target.__dragStart;
          delete e.target.__dragStart;
          if (dragStart) {
            this._moveChild(e.target, dragStart, e.target._latlng);
          }
        },
        //Internal function for removing a marker from everything.
        //dontUpdateMap: set to true if you will handle updating the map manually (for bulk functions)
        _removeLayer: function(marker, removeFromDistanceGrid, dontUpdateMap) {
          var gridClusters = this._gridClusters, gridUnclustered = this._gridUnclustered, fg = this._featureGroup, map = this._map, minZoom = Math.floor(this._map.getMinZoom());
          if (removeFromDistanceGrid) {
            this._removeFromGridUnclustered(marker, this._maxZoom);
          }
          var cluster = marker.__parent, markers = cluster._markers, otherMarker;
          this._arraySplice(markers, marker);
          while (cluster) {
            cluster._childCount--;
            cluster._boundsNeedUpdate = true;
            if (cluster._zoom < minZoom) {
              break;
            } else if (removeFromDistanceGrid && cluster._childCount <= 1) {
              otherMarker = cluster._markers[0] === marker ? cluster._markers[1] : cluster._markers[0];
              gridClusters[cluster._zoom].removeObject(cluster, map.project(cluster._cLatLng, cluster._zoom));
              gridUnclustered[cluster._zoom].addObject(otherMarker, map.project(otherMarker.getLatLng(), cluster._zoom));
              this._arraySplice(cluster.__parent._childClusters, cluster);
              cluster.__parent._markers.push(otherMarker);
              otherMarker.__parent = cluster.__parent;
              if (cluster._icon) {
                fg.removeLayer(cluster);
                if (!dontUpdateMap) {
                  fg.addLayer(otherMarker);
                }
              }
            } else {
              cluster._iconNeedsUpdate = true;
            }
            cluster = cluster.__parent;
          }
          delete marker.__parent;
        },
        _isOrIsParent: function(el, oel) {
          while (oel) {
            if (el === oel) {
              return true;
            }
            oel = oel.parentNode;
          }
          return false;
        },
        //Override L.Evented.fire
        fire: function(type, data, propagate) {
          if (data && data.layer instanceof L.MarkerCluster) {
            if (data.originalEvent && this._isOrIsParent(data.layer._icon, data.originalEvent.relatedTarget)) {
              return;
            }
            type = "cluster" + type;
          }
          L.FeatureGroup.prototype.fire.call(this, type, data, propagate);
        },
        //Override L.Evented.listens
        listens: function(type, propagate) {
          return L.FeatureGroup.prototype.listens.call(this, type, propagate) || L.FeatureGroup.prototype.listens.call(this, "cluster" + type, propagate);
        },
        //Default functionality
        _defaultIconCreateFunction: function(cluster) {
          var childCount = cluster.getChildCount();
          var c = " marker-cluster-";
          if (childCount < 10) {
            c += "small";
          } else if (childCount < 100) {
            c += "medium";
          } else {
            c += "large";
          }
          return new L.DivIcon({ html: "<div><span>" + childCount + "</span></div>", className: "marker-cluster" + c, iconSize: new L.Point(40, 40) });
        },
        _bindEvents: function() {
          var map = this._map, spiderfyOnMaxZoom = this.options.spiderfyOnMaxZoom, showCoverageOnHover = this.options.showCoverageOnHover, zoomToBoundsOnClick = this.options.zoomToBoundsOnClick, spiderfyOnEveryZoom = this.options.spiderfyOnEveryZoom;
          if (spiderfyOnMaxZoom || zoomToBoundsOnClick || spiderfyOnEveryZoom) {
            this.on("clusterclick clusterkeypress", this._zoomOrSpiderfy, this);
          }
          if (showCoverageOnHover) {
            this.on("clustermouseover", this._showCoverage, this);
            this.on("clustermouseout", this._hideCoverage, this);
            map.on("zoomend", this._hideCoverage, this);
          }
        },
        _zoomOrSpiderfy: function(e) {
          var cluster = e.layer, bottomCluster = cluster;
          if (e.type === "clusterkeypress" && e.originalEvent && e.originalEvent.keyCode !== 13) {
            return;
          }
          while (bottomCluster._childClusters.length === 1) {
            bottomCluster = bottomCluster._childClusters[0];
          }
          if (bottomCluster._zoom === this._maxZoom && bottomCluster._childCount === cluster._childCount && this.options.spiderfyOnMaxZoom) {
            cluster.spiderfy();
          } else if (this.options.zoomToBoundsOnClick) {
            cluster.zoomToBounds();
          }
          if (this.options.spiderfyOnEveryZoom) {
            cluster.spiderfy();
          }
          if (e.originalEvent && e.originalEvent.keyCode === 13) {
            this._map._container.focus();
          }
        },
        _showCoverage: function(e) {
          var map = this._map;
          if (this._inZoomAnimation) {
            return;
          }
          if (this._shownPolygon) {
            map.removeLayer(this._shownPolygon);
          }
          if (e.layer.getChildCount() > 2 && e.layer !== this._spiderfied) {
            this._shownPolygon = new L.Polygon(e.layer.getConvexHull(), this.options.polygonOptions);
            map.addLayer(this._shownPolygon);
          }
        },
        _hideCoverage: function() {
          if (this._shownPolygon) {
            this._map.removeLayer(this._shownPolygon);
            this._shownPolygon = null;
          }
        },
        _unbindEvents: function() {
          var spiderfyOnMaxZoom = this.options.spiderfyOnMaxZoom, showCoverageOnHover = this.options.showCoverageOnHover, zoomToBoundsOnClick = this.options.zoomToBoundsOnClick, spiderfyOnEveryZoom = this.options.spiderfyOnEveryZoom, map = this._map;
          if (spiderfyOnMaxZoom || zoomToBoundsOnClick || spiderfyOnEveryZoom) {
            this.off("clusterclick clusterkeypress", this._zoomOrSpiderfy, this);
          }
          if (showCoverageOnHover) {
            this.off("clustermouseover", this._showCoverage, this);
            this.off("clustermouseout", this._hideCoverage, this);
            map.off("zoomend", this._hideCoverage, this);
          }
        },
        _zoomEnd: function() {
          if (!this._map) {
            return;
          }
          this._mergeSplitClusters();
          this._zoom = Math.round(this._map._zoom);
          this._currentShownBounds = this._getExpandedVisibleBounds();
        },
        _moveEnd: function() {
          if (this._inZoomAnimation) {
            return;
          }
          var newBounds = this._getExpandedVisibleBounds();
          this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), this._zoom, newBounds);
          this._topClusterLevel._recursivelyAddChildrenToMap(null, Math.round(this._map._zoom), newBounds);
          this._currentShownBounds = newBounds;
          return;
        },
        _generateInitialClusters: function() {
          var maxZoom = Math.ceil(this._map.getMaxZoom()), minZoom = Math.floor(this._map.getMinZoom()), radius = this.options.maxClusterRadius, radiusFn = radius;
          if (typeof radius !== "function") {
            radiusFn = function() {
              return radius;
            };
          }
          if (this.options.disableClusteringAtZoom !== null) {
            maxZoom = this.options.disableClusteringAtZoom - 1;
          }
          this._maxZoom = maxZoom;
          this._gridClusters = {};
          this._gridUnclustered = {};
          for (var zoom = maxZoom; zoom >= minZoom; zoom--) {
            this._gridClusters[zoom] = new L.DistanceGrid(radiusFn(zoom));
            this._gridUnclustered[zoom] = new L.DistanceGrid(radiusFn(zoom));
          }
          this._topClusterLevel = new this._markerCluster(this, minZoom - 1);
        },
        //Zoom: Zoom to start adding at (Pass this._maxZoom to start at the bottom)
        _addLayer: function(layer, zoom) {
          var gridClusters = this._gridClusters, gridUnclustered = this._gridUnclustered, minZoom = Math.floor(this._map.getMinZoom()), markerPoint, z;
          if (this.options.singleMarkerMode) {
            this._overrideMarkerIcon(layer);
          }
          layer.on(this._childMarkerEventHandlers, this);
          for (; zoom >= minZoom; zoom--) {
            markerPoint = this._map.project(layer.getLatLng(), zoom);
            var closest = gridClusters[zoom].getNearObject(markerPoint);
            if (closest) {
              closest._addChild(layer);
              layer.__parent = closest;
              return;
            }
            closest = gridUnclustered[zoom].getNearObject(markerPoint);
            if (closest) {
              var parent = closest.__parent;
              if (parent) {
                this._removeLayer(closest, false);
              }
              var newCluster = new this._markerCluster(this, zoom, closest, layer);
              gridClusters[zoom].addObject(newCluster, this._map.project(newCluster._cLatLng, zoom));
              closest.__parent = newCluster;
              layer.__parent = newCluster;
              var lastParent = newCluster;
              for (z = zoom - 1; z > parent._zoom; z--) {
                lastParent = new this._markerCluster(this, z, lastParent);
                gridClusters[z].addObject(lastParent, this._map.project(closest.getLatLng(), z));
              }
              parent._addChild(lastParent);
              this._removeFromGridUnclustered(closest, zoom);
              return;
            }
            gridUnclustered[zoom].addObject(layer, markerPoint);
          }
          this._topClusterLevel._addChild(layer);
          layer.__parent = this._topClusterLevel;
          return;
        },
        /**
         * Refreshes the icon of all "dirty" visible clusters.
         * Non-visible "dirty" clusters will be updated when they are added to the map.
         * @private
         */
        _refreshClustersIcons: function() {
          this._featureGroup.eachLayer(function(c) {
            if (c instanceof L.MarkerCluster && c._iconNeedsUpdate) {
              c._updateIcon();
            }
          });
        },
        //Enqueue code to fire after the marker expand/contract has happened
        _enqueue: function(fn) {
          this._queue.push(fn);
          if (!this._queueTimeout) {
            this._queueTimeout = setTimeout(L.bind(this._processQueue, this), 300);
          }
        },
        _processQueue: function() {
          for (var i = 0; i < this._queue.length; i++) {
            this._queue[i].call(this);
          }
          this._queue.length = 0;
          clearTimeout(this._queueTimeout);
          this._queueTimeout = null;
        },
        //Merge and split any existing clusters that are too big or small
        _mergeSplitClusters: function() {
          var mapZoom = Math.round(this._map._zoom);
          this._processQueue();
          if (this._zoom < mapZoom && this._currentShownBounds.intersects(this._getExpandedVisibleBounds())) {
            this._animationStart();
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), this._zoom, this._getExpandedVisibleBounds());
            this._animationZoomIn(this._zoom, mapZoom);
          } else if (this._zoom > mapZoom) {
            this._animationStart();
            this._animationZoomOut(this._zoom, mapZoom);
          } else {
            this._moveEnd();
          }
        },
        //Gets the maps visible bounds expanded in each direction by the size of the screen (so the user cannot see an area we do not cover in one pan)
        _getExpandedVisibleBounds: function() {
          if (!this.options.removeOutsideVisibleBounds) {
            return this._mapBoundsInfinite;
          } else if (L.Browser.mobile) {
            return this._checkBoundsMaxLat(this._map.getBounds());
          }
          return this._checkBoundsMaxLat(this._map.getBounds().pad(1));
        },
        /**
         * Expands the latitude to Infinity (or -Infinity) if the input bounds reach the map projection maximum defined latitude
         * (in the case of Web/Spherical Mercator, it is 85.0511287798 / see https://en.wikipedia.org/wiki/Web_Mercator#Formulas).
         * Otherwise, the removeOutsideVisibleBounds option will remove markers beyond that limit, whereas the same markers without
         * this option (or outside MCG) will have their position floored (ceiled) by the projection and rendered at that limit,
         * making the user think that MCG "eats" them and never displays them again.
         * @param bounds L.LatLngBounds
         * @returns {L.LatLngBounds}
         * @private
         */
        _checkBoundsMaxLat: function(bounds) {
          var maxLat = this._maxLat;
          if (maxLat !== void 0) {
            if (bounds.getNorth() >= maxLat) {
              bounds._northEast.lat = Infinity;
            }
            if (bounds.getSouth() <= -maxLat) {
              bounds._southWest.lat = -Infinity;
            }
          }
          return bounds;
        },
        //Shared animation code
        _animationAddLayerNonAnimated: function(layer, newCluster) {
          if (newCluster === layer) {
            this._featureGroup.addLayer(layer);
          } else if (newCluster._childCount === 2) {
            newCluster._addToMap();
            var markers = newCluster.getAllChildMarkers();
            this._featureGroup.removeLayer(markers[0]);
            this._featureGroup.removeLayer(markers[1]);
          } else {
            newCluster._updateIcon();
          }
        },
        /**
         * Extracts individual (i.e. non-group) layers from a Layer Group.
         * @param group to extract layers from.
         * @param output {Array} in which to store the extracted layers.
         * @returns {*|Array}
         * @private
         */
        _extractNonGroupLayers: function(group, output) {
          var layers = group.getLayers(), i = 0, layer;
          output = output || [];
          for (; i < layers.length; i++) {
            layer = layers[i];
            if (layer instanceof L.LayerGroup) {
              this._extractNonGroupLayers(layer, output);
              continue;
            }
            output.push(layer);
          }
          return output;
        },
        /**
         * Implements the singleMarkerMode option.
         * @param layer Marker to re-style using the Clusters iconCreateFunction.
         * @returns {L.Icon} The newly created icon.
         * @private
         */
        _overrideMarkerIcon: function(layer) {
          var icon = layer.options.icon = this.options.iconCreateFunction({
            getChildCount: function() {
              return 1;
            },
            getAllChildMarkers: function() {
              return [layer];
            }
          });
          return icon;
        }
      });
      L.MarkerClusterGroup.include({
        _mapBoundsInfinite: new L.LatLngBounds(new L.LatLng(-Infinity, -Infinity), new L.LatLng(Infinity, Infinity))
      });
      L.MarkerClusterGroup.include({
        _noAnimation: {
          //Non Animated versions of everything
          _animationStart: function() {
          },
          _animationZoomIn: function(previousZoomLevel, newZoomLevel) {
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this.fire("animationend");
          },
          _animationZoomOut: function(previousZoomLevel, newZoomLevel) {
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this.fire("animationend");
          },
          _animationAddLayer: function(layer, newCluster) {
            this._animationAddLayerNonAnimated(layer, newCluster);
          }
        },
        _withAnimation: {
          //Animated versions here
          _animationStart: function() {
            this._map._mapPane.className += " leaflet-cluster-anim";
            this._inZoomAnimation++;
          },
          _animationZoomIn: function(previousZoomLevel, newZoomLevel) {
            var bounds = this._getExpandedVisibleBounds(), fg = this._featureGroup, minZoom = Math.floor(this._map.getMinZoom()), i;
            this._ignoreMove = true;
            this._topClusterLevel._recursively(bounds, previousZoomLevel, minZoom, function(c) {
              var startPos = c._latlng, markers = c._markers, m;
              if (!bounds.contains(startPos)) {
                startPos = null;
              }
              if (c._isSingleParent() && previousZoomLevel + 1 === newZoomLevel) {
                fg.removeLayer(c);
                c._recursivelyAddChildrenToMap(null, newZoomLevel, bounds);
              } else {
                c.clusterHide();
                c._recursivelyAddChildrenToMap(startPos, newZoomLevel, bounds);
              }
              for (i = markers.length - 1; i >= 0; i--) {
                m = markers[i];
                if (!bounds.contains(m._latlng)) {
                  fg.removeLayer(m);
                }
              }
            });
            this._forceLayout();
            this._topClusterLevel._recursivelyBecomeVisible(bounds, newZoomLevel);
            fg.eachLayer(function(n) {
              if (!(n instanceof L.MarkerCluster) && n._icon) {
                n.clusterShow();
              }
            });
            this._topClusterLevel._recursively(bounds, previousZoomLevel, newZoomLevel, function(c) {
              c._recursivelyRestoreChildPositions(newZoomLevel);
            });
            this._ignoreMove = false;
            this._enqueue(function() {
              this._topClusterLevel._recursively(bounds, previousZoomLevel, minZoom, function(c) {
                fg.removeLayer(c);
                c.clusterShow();
              });
              this._animationEnd();
            });
          },
          _animationZoomOut: function(previousZoomLevel, newZoomLevel) {
            this._animationZoomOutSingle(this._topClusterLevel, previousZoomLevel - 1, newZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel, this._getExpandedVisibleBounds());
          },
          _animationAddLayer: function(layer, newCluster) {
            var me = this, fg = this._featureGroup;
            fg.addLayer(layer);
            if (newCluster !== layer) {
              if (newCluster._childCount > 2) {
                newCluster._updateIcon();
                this._forceLayout();
                this._animationStart();
                layer._setPos(this._map.latLngToLayerPoint(newCluster.getLatLng()));
                layer.clusterHide();
                this._enqueue(function() {
                  fg.removeLayer(layer);
                  layer.clusterShow();
                  me._animationEnd();
                });
              } else {
                this._forceLayout();
                me._animationStart();
                me._animationZoomOutSingle(newCluster, this._map.getMaxZoom(), this._zoom);
              }
            }
          }
        },
        // Private methods for animated versions.
        _animationZoomOutSingle: function(cluster, previousZoomLevel, newZoomLevel) {
          var bounds = this._getExpandedVisibleBounds(), minZoom = Math.floor(this._map.getMinZoom());
          cluster._recursivelyAnimateChildrenInAndAddSelfToMap(bounds, minZoom, previousZoomLevel + 1, newZoomLevel);
          var me = this;
          this._forceLayout();
          cluster._recursivelyBecomeVisible(bounds, newZoomLevel);
          this._enqueue(function() {
            if (cluster._childCount === 1) {
              var m = cluster._markers[0];
              this._ignoreMove = true;
              m.setLatLng(m.getLatLng());
              this._ignoreMove = false;
              if (m.clusterShow) {
                m.clusterShow();
              }
            } else {
              cluster._recursively(bounds, newZoomLevel, minZoom, function(c) {
                c._recursivelyRemoveChildrenFromMap(bounds, minZoom, previousZoomLevel + 1);
              });
            }
            me._animationEnd();
          });
        },
        _animationEnd: function() {
          if (this._map) {
            this._map._mapPane.className = this._map._mapPane.className.replace(" leaflet-cluster-anim", "");
          }
          this._inZoomAnimation--;
          this.fire("animationend");
        },
        //Force a browser layout of stuff in the map
        // Should apply the current opacity and location to all elements so we can update them again for an animation
        _forceLayout: function() {
          L.Util.falseFn(document.body.offsetWidth);
        }
      });
      L.markerClusterGroup = function(options) {
        return new L.MarkerClusterGroup(options);
      };
      var MarkerCluster = L.MarkerCluster = L.Marker.extend({
        options: L.Icon.prototype.options,
        initialize: function(group, zoom, a, b) {
          L.Marker.prototype.initialize.call(
            this,
            a ? a._cLatLng || a.getLatLng() : new L.LatLng(0, 0),
            { icon: this, pane: group.options.clusterPane }
          );
          this._group = group;
          this._zoom = zoom;
          this._markers = [];
          this._childClusters = [];
          this._childCount = 0;
          this._iconNeedsUpdate = true;
          this._boundsNeedUpdate = true;
          this._bounds = new L.LatLngBounds();
          if (a) {
            this._addChild(a);
          }
          if (b) {
            this._addChild(b);
          }
        },
        //Recursively retrieve all child markers of this cluster
        getAllChildMarkers: function(storageArray, ignoreDraggedMarker) {
          storageArray = storageArray || [];
          for (var i = this._childClusters.length - 1; i >= 0; i--) {
            this._childClusters[i].getAllChildMarkers(storageArray, ignoreDraggedMarker);
          }
          for (var j = this._markers.length - 1; j >= 0; j--) {
            if (ignoreDraggedMarker && this._markers[j].__dragStart) {
              continue;
            }
            storageArray.push(this._markers[j]);
          }
          return storageArray;
        },
        //Returns the count of how many child markers we have
        getChildCount: function() {
          return this._childCount;
        },
        //Zoom to the minimum of showing all of the child markers, or the extents of this cluster
        zoomToBounds: function(fitBoundsOptions) {
          var childClusters = this._childClusters.slice(), map = this._group._map, boundsZoom = map.getBoundsZoom(this._bounds), zoom = this._zoom + 1, mapZoom = map.getZoom(), i;
          while (childClusters.length > 0 && boundsZoom > zoom) {
            zoom++;
            var newClusters = [];
            for (i = 0; i < childClusters.length; i++) {
              newClusters = newClusters.concat(childClusters[i]._childClusters);
            }
            childClusters = newClusters;
          }
          if (boundsZoom > zoom) {
            this._group._map.setView(this._latlng, zoom);
          } else if (boundsZoom <= mapZoom) {
            this._group._map.setView(this._latlng, mapZoom + 1);
          } else {
            this._group._map.fitBounds(this._bounds, fitBoundsOptions);
          }
        },
        getBounds: function() {
          var bounds = new L.LatLngBounds();
          bounds.extend(this._bounds);
          return bounds;
        },
        _updateIcon: function() {
          this._iconNeedsUpdate = true;
          if (this._icon) {
            this.setIcon(this);
          }
        },
        //Cludge for Icon, we pretend to be an icon for performance
        createIcon: function() {
          if (this._iconNeedsUpdate) {
            this._iconObj = this._group.options.iconCreateFunction(this);
            this._iconNeedsUpdate = false;
          }
          return this._iconObj.createIcon();
        },
        createShadow: function() {
          return this._iconObj.createShadow();
        },
        _addChild: function(new1, isNotificationFromChild) {
          this._iconNeedsUpdate = true;
          this._boundsNeedUpdate = true;
          this._setClusterCenter(new1);
          if (new1 instanceof L.MarkerCluster) {
            if (!isNotificationFromChild) {
              this._childClusters.push(new1);
              new1.__parent = this;
            }
            this._childCount += new1._childCount;
          } else {
            if (!isNotificationFromChild) {
              this._markers.push(new1);
            }
            this._childCount++;
          }
          if (this.__parent) {
            this.__parent._addChild(new1, true);
          }
        },
        /**
         * Makes sure the cluster center is set. If not, uses the child center if it is a cluster, or the marker position.
         * @param child L.MarkerCluster|L.Marker that will be used as cluster center if not defined yet.
         * @private
         */
        _setClusterCenter: function(child) {
          if (!this._cLatLng) {
            this._cLatLng = child._cLatLng || child._latlng;
          }
        },
        /**
         * Assigns impossible bounding values so that the next extend entirely determines the new bounds.
         * This method avoids having to trash the previous L.LatLngBounds object and to create a new one, which is much slower for this class.
         * As long as the bounds are not extended, most other methods would probably fail, as they would with bounds initialized but not extended.
         * @private
         */
        _resetBounds: function() {
          var bounds = this._bounds;
          if (bounds._southWest) {
            bounds._southWest.lat = Infinity;
            bounds._southWest.lng = Infinity;
          }
          if (bounds._northEast) {
            bounds._northEast.lat = -Infinity;
            bounds._northEast.lng = -Infinity;
          }
        },
        _recalculateBounds: function() {
          var markers = this._markers, childClusters = this._childClusters, latSum = 0, lngSum = 0, totalCount = this._childCount, i, child, childLatLng, childCount;
          if (totalCount === 0) {
            return;
          }
          this._resetBounds();
          for (i = 0; i < markers.length; i++) {
            childLatLng = markers[i]._latlng;
            this._bounds.extend(childLatLng);
            latSum += childLatLng.lat;
            lngSum += childLatLng.lng;
          }
          for (i = 0; i < childClusters.length; i++) {
            child = childClusters[i];
            if (child._boundsNeedUpdate) {
              child._recalculateBounds();
            }
            this._bounds.extend(child._bounds);
            childLatLng = child._wLatLng;
            childCount = child._childCount;
            latSum += childLatLng.lat * childCount;
            lngSum += childLatLng.lng * childCount;
          }
          this._latlng = this._wLatLng = new L.LatLng(latSum / totalCount, lngSum / totalCount);
          this._boundsNeedUpdate = false;
        },
        //Set our markers position as given and add it to the map
        _addToMap: function(startPos) {
          if (startPos) {
            this._backupLatlng = this._latlng;
            this.setLatLng(startPos);
          }
          this._group._featureGroup.addLayer(this);
        },
        _recursivelyAnimateChildrenIn: function(bounds, center, maxZoom) {
          this._recursively(
            bounds,
            this._group._map.getMinZoom(),
            maxZoom - 1,
            function(c) {
              var markers = c._markers, i, m;
              for (i = markers.length - 1; i >= 0; i--) {
                m = markers[i];
                if (m._icon) {
                  m._setPos(center);
                  m.clusterHide();
                }
              }
            },
            function(c) {
              var childClusters = c._childClusters, j, cm;
              for (j = childClusters.length - 1; j >= 0; j--) {
                cm = childClusters[j];
                if (cm._icon) {
                  cm._setPos(center);
                  cm.clusterHide();
                }
              }
            }
          );
        },
        _recursivelyAnimateChildrenInAndAddSelfToMap: function(bounds, mapMinZoom, previousZoomLevel, newZoomLevel) {
          this._recursively(
            bounds,
            newZoomLevel,
            mapMinZoom,
            function(c) {
              c._recursivelyAnimateChildrenIn(bounds, c._group._map.latLngToLayerPoint(c.getLatLng()).round(), previousZoomLevel);
              if (c._isSingleParent() && previousZoomLevel - 1 === newZoomLevel) {
                c.clusterShow();
                c._recursivelyRemoveChildrenFromMap(bounds, mapMinZoom, previousZoomLevel);
              } else {
                c.clusterHide();
              }
              c._addToMap();
            }
          );
        },
        _recursivelyBecomeVisible: function(bounds, zoomLevel) {
          this._recursively(bounds, this._group._map.getMinZoom(), zoomLevel, null, function(c) {
            c.clusterShow();
          });
        },
        _recursivelyAddChildrenToMap: function(startPos, zoomLevel, bounds) {
          this._recursively(
            bounds,
            this._group._map.getMinZoom() - 1,
            zoomLevel,
            function(c) {
              if (zoomLevel === c._zoom) {
                return;
              }
              for (var i = c._markers.length - 1; i >= 0; i--) {
                var nm = c._markers[i];
                if (!bounds.contains(nm._latlng)) {
                  continue;
                }
                if (startPos) {
                  nm._backupLatlng = nm.getLatLng();
                  nm.setLatLng(startPos);
                  if (nm.clusterHide) {
                    nm.clusterHide();
                  }
                }
                c._group._featureGroup.addLayer(nm);
              }
            },
            function(c) {
              c._addToMap(startPos);
            }
          );
        },
        _recursivelyRestoreChildPositions: function(zoomLevel) {
          for (var i = this._markers.length - 1; i >= 0; i--) {
            var nm = this._markers[i];
            if (nm._backupLatlng) {
              nm.setLatLng(nm._backupLatlng);
              delete nm._backupLatlng;
            }
          }
          if (zoomLevel - 1 === this._zoom) {
            for (var j = this._childClusters.length - 1; j >= 0; j--) {
              this._childClusters[j]._restorePosition();
            }
          } else {
            for (var k = this._childClusters.length - 1; k >= 0; k--) {
              this._childClusters[k]._recursivelyRestoreChildPositions(zoomLevel);
            }
          }
        },
        _restorePosition: function() {
          if (this._backupLatlng) {
            this.setLatLng(this._backupLatlng);
            delete this._backupLatlng;
          }
        },
        //exceptBounds: If set, don't remove any markers/clusters in it
        _recursivelyRemoveChildrenFromMap: function(previousBounds, mapMinZoom, zoomLevel, exceptBounds) {
          var m, i;
          this._recursively(
            previousBounds,
            mapMinZoom - 1,
            zoomLevel - 1,
            function(c) {
              for (i = c._markers.length - 1; i >= 0; i--) {
                m = c._markers[i];
                if (!exceptBounds || !exceptBounds.contains(m._latlng)) {
                  c._group._featureGroup.removeLayer(m);
                  if (m.clusterShow) {
                    m.clusterShow();
                  }
                }
              }
            },
            function(c) {
              for (i = c._childClusters.length - 1; i >= 0; i--) {
                m = c._childClusters[i];
                if (!exceptBounds || !exceptBounds.contains(m._latlng)) {
                  c._group._featureGroup.removeLayer(m);
                  if (m.clusterShow) {
                    m.clusterShow();
                  }
                }
              }
            }
          );
        },
        //Run the given functions recursively to this and child clusters
        // boundsToApplyTo: a L.LatLngBounds representing the bounds of what clusters to recurse in to
        // zoomLevelToStart: zoom level to start running functions (inclusive)
        // zoomLevelToStop: zoom level to stop running functions (inclusive)
        // runAtEveryLevel: function that takes an L.MarkerCluster as an argument that should be applied on every level
        // runAtBottomLevel: function that takes an L.MarkerCluster as an argument that should be applied at only the bottom level
        _recursively: function(boundsToApplyTo, zoomLevelToStart, zoomLevelToStop, runAtEveryLevel, runAtBottomLevel) {
          var childClusters = this._childClusters, zoom = this._zoom, i, c;
          if (zoomLevelToStart <= zoom) {
            if (runAtEveryLevel) {
              runAtEveryLevel(this);
            }
            if (runAtBottomLevel && zoom === zoomLevelToStop) {
              runAtBottomLevel(this);
            }
          }
          if (zoom < zoomLevelToStart || zoom < zoomLevelToStop) {
            for (i = childClusters.length - 1; i >= 0; i--) {
              c = childClusters[i];
              if (c._boundsNeedUpdate) {
                c._recalculateBounds();
              }
              if (boundsToApplyTo.intersects(c._bounds)) {
                c._recursively(boundsToApplyTo, zoomLevelToStart, zoomLevelToStop, runAtEveryLevel, runAtBottomLevel);
              }
            }
          }
        },
        //Returns true if we are the parent of only one cluster and that cluster is the same as us
        _isSingleParent: function() {
          return this._childClusters.length > 0 && this._childClusters[0]._childCount === this._childCount;
        }
      });
      L.Marker.include({
        clusterHide: function() {
          var backup = this.options.opacity;
          this.setOpacity(0);
          this.options.opacity = backup;
          return this;
        },
        clusterShow: function() {
          return this.setOpacity(this.options.opacity);
        }
      });
      L.DistanceGrid = function(cellSize) {
        this._cellSize = cellSize;
        this._sqCellSize = cellSize * cellSize;
        this._grid = {};
        this._objectPoint = {};
      };
      L.DistanceGrid.prototype = {
        addObject: function(obj, point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), grid = this._grid, row = grid[y] = grid[y] || {}, cell = row[x] = row[x] || [], stamp = L.Util.stamp(obj);
          this._objectPoint[stamp] = point;
          cell.push(obj);
        },
        updateObject: function(obj, point) {
          this.removeObject(obj);
          this.addObject(obj, point);
        },
        //Returns true if the object was found
        removeObject: function(obj, point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), grid = this._grid, row = grid[y] = grid[y] || {}, cell = row[x] = row[x] || [], i, len;
          delete this._objectPoint[L.Util.stamp(obj)];
          for (i = 0, len = cell.length; i < len; i++) {
            if (cell[i] === obj) {
              cell.splice(i, 1);
              if (len === 1) {
                delete row[x];
              }
              return true;
            }
          }
        },
        eachObject: function(fn, context) {
          var i, j, k, len, row, cell, removed, grid = this._grid;
          for (i in grid) {
            row = grid[i];
            for (j in row) {
              cell = row[j];
              for (k = 0, len = cell.length; k < len; k++) {
                removed = fn.call(context, cell[k]);
                if (removed) {
                  k--;
                  len--;
                }
              }
            }
          }
        },
        getNearObject: function(point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), i, j, k, row, cell, len, obj, dist, objectPoint = this._objectPoint, closestDistSq = this._sqCellSize, closest = null;
          for (i = y - 1; i <= y + 1; i++) {
            row = this._grid[i];
            if (row) {
              for (j = x - 1; j <= x + 1; j++) {
                cell = row[j];
                if (cell) {
                  for (k = 0, len = cell.length; k < len; k++) {
                    obj = cell[k];
                    dist = this._sqDist(objectPoint[L.Util.stamp(obj)], point);
                    if (dist < closestDistSq || dist <= closestDistSq && closest === null) {
                      closestDistSq = dist;
                      closest = obj;
                    }
                  }
                }
              }
            }
          }
          return closest;
        },
        _getCoord: function(x) {
          var coord = Math.floor(x / this._cellSize);
          return isFinite(coord) ? coord : x;
        },
        _sqDist: function(p, p2) {
          var dx = p2.x - p.x, dy = p2.y - p.y;
          return dx * dx + dy * dy;
        }
      };
      (function() {
        L.QuickHull = {
          /*
           * @param {Object} cpt a point to be measured from the baseline
           * @param {Array} bl the baseline, as represented by a two-element
           *   array of latlng objects.
           * @returns {Number} an approximate distance measure
           */
          getDistant: function(cpt, bl) {
            var vY = bl[1].lat - bl[0].lat, vX = bl[0].lng - bl[1].lng;
            return vX * (cpt.lat - bl[0].lat) + vY * (cpt.lng - bl[0].lng);
          },
          /*
           * @param {Array} baseLine a two-element array of latlng objects
           *   representing the baseline to project from
           * @param {Array} latLngs an array of latlng objects
           * @returns {Object} the maximum point and all new points to stay
           *   in consideration for the hull.
           */
          findMostDistantPointFromBaseLine: function(baseLine, latLngs) {
            var maxD = 0, maxPt = null, newPoints = [], i, pt, d;
            for (i = latLngs.length - 1; i >= 0; i--) {
              pt = latLngs[i];
              d = this.getDistant(pt, baseLine);
              if (d > 0) {
                newPoints.push(pt);
              } else {
                continue;
              }
              if (d > maxD) {
                maxD = d;
                maxPt = pt;
              }
            }
            return { maxPoint: maxPt, newPoints };
          },
          /*
           * Given a baseline, compute the convex hull of latLngs as an array
           * of latLngs.
           *
           * @param {Array} latLngs
           * @returns {Array}
           */
          buildConvexHull: function(baseLine, latLngs) {
            var convexHullBaseLines = [], t = this.findMostDistantPointFromBaseLine(baseLine, latLngs);
            if (t.maxPoint) {
              convexHullBaseLines = convexHullBaseLines.concat(
                this.buildConvexHull([baseLine[0], t.maxPoint], t.newPoints)
              );
              convexHullBaseLines = convexHullBaseLines.concat(
                this.buildConvexHull([t.maxPoint, baseLine[1]], t.newPoints)
              );
              return convexHullBaseLines;
            } else {
              return [baseLine[0]];
            }
          },
          /*
           * Given an array of latlngs, compute a convex hull as an array
           * of latlngs
           *
           * @param {Array} latLngs
           * @returns {Array}
           */
          getConvexHull: function(latLngs) {
            var maxLat = false, minLat = false, maxLng = false, minLng = false, maxLatPt = null, minLatPt = null, maxLngPt = null, minLngPt = null, maxPt = null, minPt = null, i;
            for (i = latLngs.length - 1; i >= 0; i--) {
              var pt = latLngs[i];
              if (maxLat === false || pt.lat > maxLat) {
                maxLatPt = pt;
                maxLat = pt.lat;
              }
              if (minLat === false || pt.lat < minLat) {
                minLatPt = pt;
                minLat = pt.lat;
              }
              if (maxLng === false || pt.lng > maxLng) {
                maxLngPt = pt;
                maxLng = pt.lng;
              }
              if (minLng === false || pt.lng < minLng) {
                minLngPt = pt;
                minLng = pt.lng;
              }
            }
            if (minLat !== maxLat) {
              minPt = minLatPt;
              maxPt = maxLatPt;
            } else {
              minPt = minLngPt;
              maxPt = maxLngPt;
            }
            var ch = [].concat(
              this.buildConvexHull([minPt, maxPt], latLngs),
              this.buildConvexHull([maxPt, minPt], latLngs)
            );
            return ch;
          }
        };
      })();
      L.MarkerCluster.include({
        getConvexHull: function() {
          var childMarkers = this.getAllChildMarkers(), points = [], p, i;
          for (i = childMarkers.length - 1; i >= 0; i--) {
            p = childMarkers[i].getLatLng();
            points.push(p);
          }
          return L.QuickHull.getConvexHull(points);
        }
      });
      L.MarkerCluster.include({
        _2PI: Math.PI * 2,
        _circleFootSeparation: 25,
        //related to circumference of circle
        _circleStartAngle: 0,
        _spiralFootSeparation: 28,
        //related to size of spiral (experiment!)
        _spiralLengthStart: 11,
        _spiralLengthFactor: 5,
        _circleSpiralSwitchover: 9,
        //show spiral instead of circle from this marker count upwards.
        // 0 -> always spiral; Infinity -> always circle
        spiderfy: function() {
          if (this._group._spiderfied === this || this._group._inZoomAnimation) {
            return;
          }
          var childMarkers = this.getAllChildMarkers(null, true), group = this._group, map = group._map, center = map.latLngToLayerPoint(this._latlng), positions;
          this._group._unspiderfy();
          this._group._spiderfied = this;
          if (this._group.options.spiderfyShapePositions) {
            positions = this._group.options.spiderfyShapePositions(childMarkers.length, center);
          } else if (childMarkers.length >= this._circleSpiralSwitchover) {
            positions = this._generatePointsSpiral(childMarkers.length, center);
          } else {
            center.y += 10;
            positions = this._generatePointsCircle(childMarkers.length, center);
          }
          this._animationSpiderfy(childMarkers, positions);
        },
        unspiderfy: function(zoomDetails) {
          if (this._group._inZoomAnimation) {
            return;
          }
          this._animationUnspiderfy(zoomDetails);
          this._group._spiderfied = null;
        },
        _generatePointsCircle: function(count, centerPt) {
          var circumference = this._group.options.spiderfyDistanceMultiplier * this._circleFootSeparation * (2 + count), legLength = circumference / this._2PI, angleStep = this._2PI / count, res = [], i, angle;
          legLength = Math.max(legLength, 35);
          res.length = count;
          for (i = 0; i < count; i++) {
            angle = this._circleStartAngle + i * angleStep;
            res[i] = new L.Point(centerPt.x + legLength * Math.cos(angle), centerPt.y + legLength * Math.sin(angle))._round();
          }
          return res;
        },
        _generatePointsSpiral: function(count, centerPt) {
          var spiderfyDistanceMultiplier = this._group.options.spiderfyDistanceMultiplier, legLength = spiderfyDistanceMultiplier * this._spiralLengthStart, separation = spiderfyDistanceMultiplier * this._spiralFootSeparation, lengthFactor = spiderfyDistanceMultiplier * this._spiralLengthFactor * this._2PI, angle = 0, res = [], i;
          res.length = count;
          for (i = count; i >= 0; i--) {
            if (i < count) {
              res[i] = new L.Point(centerPt.x + legLength * Math.cos(angle), centerPt.y + legLength * Math.sin(angle))._round();
            }
            angle += separation / legLength + i * 5e-4;
            legLength += lengthFactor / angle;
          }
          return res;
        },
        _noanimationUnspiderfy: function() {
          var group = this._group, map = group._map, fg = group._featureGroup, childMarkers = this.getAllChildMarkers(null, true), m, i;
          group._ignoreMove = true;
          this.setOpacity(1);
          for (i = childMarkers.length - 1; i >= 0; i--) {
            m = childMarkers[i];
            fg.removeLayer(m);
            if (m._preSpiderfyLatlng) {
              m.setLatLng(m._preSpiderfyLatlng);
              delete m._preSpiderfyLatlng;
            }
            if (m.setZIndexOffset) {
              m.setZIndexOffset(0);
            }
            if (m._spiderLeg) {
              map.removeLayer(m._spiderLeg);
              delete m._spiderLeg;
            }
          }
          group.fire("unspiderfied", {
            cluster: this,
            markers: childMarkers
          });
          group._ignoreMove = false;
          group._spiderfied = null;
        }
      });
      L.MarkerClusterNonAnimated = L.MarkerCluster.extend({
        _animationSpiderfy: function(childMarkers, positions) {
          var group = this._group, map = group._map, fg = group._featureGroup, legOptions = this._group.options.spiderLegPolylineOptions, i, m, leg, newPos;
          group._ignoreMove = true;
          for (i = 0; i < childMarkers.length; i++) {
            newPos = map.layerPointToLatLng(positions[i]);
            m = childMarkers[i];
            leg = new L.Polyline([this._latlng, newPos], legOptions);
            map.addLayer(leg);
            m._spiderLeg = leg;
            m._preSpiderfyLatlng = m._latlng;
            m.setLatLng(newPos);
            if (m.setZIndexOffset) {
              m.setZIndexOffset(1e6);
            }
            fg.addLayer(m);
          }
          this.setOpacity(0.3);
          group._ignoreMove = false;
          group.fire("spiderfied", {
            cluster: this,
            markers: childMarkers
          });
        },
        _animationUnspiderfy: function() {
          this._noanimationUnspiderfy();
        }
      });
      L.MarkerCluster.include({
        _animationSpiderfy: function(childMarkers, positions) {
          var me = this, group = this._group, map = group._map, fg = group._featureGroup, thisLayerLatLng = this._latlng, thisLayerPos = map.latLngToLayerPoint(thisLayerLatLng), svg = L.Path.SVG, legOptions = L.extend({}, this._group.options.spiderLegPolylineOptions), finalLegOpacity = legOptions.opacity, i, m, leg, legPath, legLength, newPos;
          if (finalLegOpacity === void 0) {
            finalLegOpacity = L.MarkerClusterGroup.prototype.options.spiderLegPolylineOptions.opacity;
          }
          if (svg) {
            legOptions.opacity = 0;
            legOptions.className = (legOptions.className || "") + " leaflet-cluster-spider-leg";
          } else {
            legOptions.opacity = finalLegOpacity;
          }
          group._ignoreMove = true;
          for (i = 0; i < childMarkers.length; i++) {
            m = childMarkers[i];
            newPos = map.layerPointToLatLng(positions[i]);
            leg = new L.Polyline([thisLayerLatLng, newPos], legOptions);
            map.addLayer(leg);
            m._spiderLeg = leg;
            if (svg) {
              legPath = leg._path;
              legLength = legPath.getTotalLength() + 0.1;
              legPath.style.strokeDasharray = legLength;
              legPath.style.strokeDashoffset = legLength;
            }
            if (m.setZIndexOffset) {
              m.setZIndexOffset(1e6);
            }
            if (m.clusterHide) {
              m.clusterHide();
            }
            fg.addLayer(m);
            if (m._setPos) {
              m._setPos(thisLayerPos);
            }
          }
          group._forceLayout();
          group._animationStart();
          for (i = childMarkers.length - 1; i >= 0; i--) {
            newPos = map.layerPointToLatLng(positions[i]);
            m = childMarkers[i];
            m._preSpiderfyLatlng = m._latlng;
            m.setLatLng(newPos);
            if (m.clusterShow) {
              m.clusterShow();
            }
            if (svg) {
              leg = m._spiderLeg;
              legPath = leg._path;
              legPath.style.strokeDashoffset = 0;
              leg.setStyle({ opacity: finalLegOpacity });
            }
          }
          this.setOpacity(0.3);
          group._ignoreMove = false;
          setTimeout(function() {
            group._animationEnd();
            group.fire("spiderfied", {
              cluster: me,
              markers: childMarkers
            });
          }, 200);
        },
        _animationUnspiderfy: function(zoomDetails) {
          var me = this, group = this._group, map = group._map, fg = group._featureGroup, thisLayerPos = zoomDetails ? map._latLngToNewLayerPoint(this._latlng, zoomDetails.zoom, zoomDetails.center) : map.latLngToLayerPoint(this._latlng), childMarkers = this.getAllChildMarkers(null, true), svg = L.Path.SVG, m, i, leg, legPath, legLength, nonAnimatable;
          group._ignoreMove = true;
          group._animationStart();
          this.setOpacity(1);
          for (i = childMarkers.length - 1; i >= 0; i--) {
            m = childMarkers[i];
            if (!m._preSpiderfyLatlng) {
              continue;
            }
            m.closePopup();
            m.setLatLng(m._preSpiderfyLatlng);
            delete m._preSpiderfyLatlng;
            nonAnimatable = true;
            if (m._setPos) {
              m._setPos(thisLayerPos);
              nonAnimatable = false;
            }
            if (m.clusterHide) {
              m.clusterHide();
              nonAnimatable = false;
            }
            if (nonAnimatable) {
              fg.removeLayer(m);
            }
            if (svg) {
              leg = m._spiderLeg;
              legPath = leg._path;
              legLength = legPath.getTotalLength() + 0.1;
              legPath.style.strokeDashoffset = legLength;
              leg.setStyle({ opacity: 0 });
            }
          }
          group._ignoreMove = false;
          setTimeout(function() {
            var stillThereChildCount = 0;
            for (i = childMarkers.length - 1; i >= 0; i--) {
              m = childMarkers[i];
              if (m._spiderLeg) {
                stillThereChildCount++;
              }
            }
            for (i = childMarkers.length - 1; i >= 0; i--) {
              m = childMarkers[i];
              if (!m._spiderLeg) {
                continue;
              }
              if (m.clusterShow) {
                m.clusterShow();
              }
              if (m.setZIndexOffset) {
                m.setZIndexOffset(0);
              }
              if (stillThereChildCount > 1) {
                fg.removeLayer(m);
              }
              map.removeLayer(m._spiderLeg);
              delete m._spiderLeg;
            }
            group._animationEnd();
            group.fire("unspiderfied", {
              cluster: me,
              markers: childMarkers
            });
          }, 200);
        }
      });
      L.MarkerClusterGroup.include({
        //The MarkerCluster currently spiderfied (if any)
        _spiderfied: null,
        unspiderfy: function() {
          this._unspiderfy.apply(this, arguments);
        },
        _spiderfierOnAdd: function() {
          this._map.on("click", this._unspiderfyWrapper, this);
          if (this._map.options.zoomAnimation) {
            this._map.on("zoomstart", this._unspiderfyZoomStart, this);
          }
          this._map.on("zoomend", this._noanimationUnspiderfy, this);
          if (!L.Browser.touch) {
            this._map.getRenderer(this);
          }
        },
        _spiderfierOnRemove: function() {
          this._map.off("click", this._unspiderfyWrapper, this);
          this._map.off("zoomstart", this._unspiderfyZoomStart, this);
          this._map.off("zoomanim", this._unspiderfyZoomAnim, this);
          this._map.off("zoomend", this._noanimationUnspiderfy, this);
          this._noanimationUnspiderfy();
        },
        //On zoom start we add a zoomanim handler so that we are guaranteed to be last (after markers are animated)
        //This means we can define the animation they do rather than Markers doing an animation to their actual location
        _unspiderfyZoomStart: function() {
          if (!this._map) {
            return;
          }
          this._map.on("zoomanim", this._unspiderfyZoomAnim, this);
        },
        _unspiderfyZoomAnim: function(zoomDetails) {
          if (L.DomUtil.hasClass(this._map._mapPane, "leaflet-touching")) {
            return;
          }
          this._map.off("zoomanim", this._unspiderfyZoomAnim, this);
          this._unspiderfy(zoomDetails);
        },
        _unspiderfyWrapper: function() {
          this._unspiderfy();
        },
        _unspiderfy: function(zoomDetails) {
          if (this._spiderfied) {
            this._spiderfied.unspiderfy(zoomDetails);
          }
        },
        _noanimationUnspiderfy: function() {
          if (this._spiderfied) {
            this._spiderfied._noanimationUnspiderfy();
          }
        },
        //If the given layer is currently being spiderfied then we unspiderfy it so it isn't on the map anymore etc
        _unspiderfyLayer: function(layer) {
          if (layer._spiderLeg) {
            this._featureGroup.removeLayer(layer);
            if (layer.clusterShow) {
              layer.clusterShow();
            }
            if (layer.setZIndexOffset) {
              layer.setZIndexOffset(0);
            }
            this._map.removeLayer(layer._spiderLeg);
            delete layer._spiderLeg;
          }
        }
      });
      L.MarkerClusterGroup.include({
        /**
         * Updates the icon of all clusters which are parents of the given marker(s).
         * In singleMarkerMode, also updates the given marker(s) icon.
         * @param layers L.MarkerClusterGroup|L.LayerGroup|Array(L.Marker)|Map(L.Marker)|
         * L.MarkerCluster|L.Marker (optional) list of markers (or single marker) whose parent
         * clusters need to be updated. If not provided, retrieves all child markers of this.
         * @returns {L.MarkerClusterGroup}
         */
        refreshClusters: function(layers) {
          if (!layers) {
            layers = this._topClusterLevel.getAllChildMarkers();
          } else if (layers instanceof L.MarkerClusterGroup) {
            layers = layers._topClusterLevel.getAllChildMarkers();
          } else if (layers instanceof L.LayerGroup) {
            layers = layers._layers;
          } else if (layers instanceof L.MarkerCluster) {
            layers = layers.getAllChildMarkers();
          } else if (layers instanceof L.Marker) {
            layers = [layers];
          }
          this._flagParentsIconsNeedUpdate(layers);
          this._refreshClustersIcons();
          if (this.options.singleMarkerMode) {
            this._refreshSingleMarkerModeMarkers(layers);
          }
          return this;
        },
        /**
         * Simply flags all parent clusters of the given markers as having a "dirty" icon.
         * @param layers Array(L.Marker)|Map(L.Marker) list of markers.
         * @private
         */
        _flagParentsIconsNeedUpdate: function(layers) {
          var id, parent;
          for (id in layers) {
            parent = layers[id].__parent;
            while (parent) {
              parent._iconNeedsUpdate = true;
              parent = parent.__parent;
            }
          }
        },
        /**
         * Re-draws the icon of the supplied markers.
         * To be used in singleMarkerMode only.
         * @param layers Array(L.Marker)|Map(L.Marker) list of markers.
         * @private
         */
        _refreshSingleMarkerModeMarkers: function(layers) {
          var id, layer;
          for (id in layers) {
            layer = layers[id];
            if (this.hasLayer(layer)) {
              layer.setIcon(this._overrideMarkerIcon(layer));
            }
          }
        }
      });
      L.Marker.include({
        /**
         * Updates the given options in the marker's icon and refreshes the marker.
         * @param options map object of icon options.
         * @param directlyRefreshClusters boolean (optional) true to trigger
         * MCG.refreshClustersOf() right away with this single marker.
         * @returns {L.Marker}
         */
        refreshIconOptions: function(options, directlyRefreshClusters) {
          var icon = this.options.icon;
          L.setOptions(icon, options);
          this.setIcon(icon);
          if (directlyRefreshClusters && this.__parent) {
            this.__parent._group.refreshClusters(this);
          }
          return this;
        }
      });
      exports2.MarkerClusterGroup = MarkerClusterGroup;
      exports2.MarkerCluster = MarkerCluster;
      Object.defineProperty(exports2, "__esModule", { value: true });
    });
  }
});
export default require_leaflet_markercluster_src();
//# sourceMappingURL=leaflet__markercluster.js.map
